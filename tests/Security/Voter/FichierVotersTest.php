<?php

declare(strict_types=1);

namespace App\Tests\Security\Voter;

use App\Entity\BSGroupe;
use App\Entity\BSMembre;
use App\Tests\Support\InMemoryFichierTrait;
use App\Tests\Support\VoterTestTrait;
use Doctrine\Common\Collections\ArrayCollection;
use NetBS\FichierBundle\Entity\Adresse;
use NetBS\FichierBundle\Entity\Attribution;
use NetBS\FichierBundle\Entity\Distinction;
use NetBS\FichierBundle\Entity\Famille;
use NetBS\FichierBundle\Entity\Fonction;
use NetBS\FichierBundle\Entity\Geniteur;
use NetBS\FichierBundle\Entity\ObtentionDistinction;
use NetBS\FichierBundle\Service\ContactManager;
use NetBS\FichierBundle\Voter\AttributionVoter;
use NetBS\FichierBundle\Voter\ContactVoter;
use NetBS\FichierBundle\Voter\GroupeVoter;
use NetBS\FichierBundle\Voter\MembreFamilleVoter;
use NetBS\FichierBundle\Voter\ObtentionDistinctionVoter;
use NetBS\FichierBundle\Voter\StructureVoter;
use NetBS\SecureBundle\Entity\Autorisation;
use NetBS\SecureBundle\Voter\CRUD;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Groupe-scoped rights: a role on a fonction (or an autorisation) in a groupe
 * grants that CRUD operation on the groupe, its sub-groupes and their membres.
 */
final class FichierVotersTest extends KernelTestCase
{
    use InMemoryFichierTrait;
    use VoterTestTrait;

    private BSGroupe $brigade;
    private BSGroupe $troupe;
    private BSGroupe $other;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->brigade = $this->groupe(1);
        $this->troupe  = $this->groupe(2, parent: $this->brigade);
        $this->other   = $this->groupe(3);
    }

    private function fonctionWith(string ...$roles): Fonction
    {
        static $id = 100;
        $fonction = $this->fonction($id++);
        foreach ($roles as $role) {
            $fonction->addRole($this->role($role));
        }

        return $fonction;
    }

    /** A user whose membre holds a fonction with the given roles in the groupe. */
    private function chefOf(BSGroupe $groupe, string ...$roles): \App\Entity\BSUser
    {
        static $id = 500;
        $membre = $this->membre($id++);
        $this->attribute($membre, $groupe, $this->fonctionWith(...$roles));

        return $this->user(membre: $membre);
    }

    private function membreIn(BSGroupe $groupe, int $id): BSMembre
    {
        $membre = $this->membre($id);
        $this->attribute($membre, $groupe, $this->fonctionWith());

        return $membre;
    }

    // --- GroupeVoter -------------------------------------------------------

    public function test_fonction_role_grants_only_that_operation_on_the_groupe(): void
    {
        $voter = new GroupeVoter($this->fichierConfig());
        $chef  = $this->chefOf($this->troupe, 'ROLE_READ');

        $this->assertGranted($this->vote($voter, $chef, $this->troupe, CRUD::READ));
        $this->assertDenied($this->vote($voter, $chef, $this->troupe, CRUD::UPDATE));
        $this->assertDenied($this->vote($voter, $chef, $this->troupe, CRUD::DELETE));
    }

    public function test_rights_flow_down_to_sub_groupes_not_up_or_sideways(): void
    {
        $voter = new GroupeVoter($this->fichierConfig());

        $brigadeChef = $this->chefOf($this->brigade, 'ROLE_UPDATE');
        $this->assertGranted($this->vote($voter, $brigadeChef, $this->troupe, CRUD::UPDATE));

        $troupeChef = $this->chefOf($this->troupe, 'ROLE_UPDATE');
        $this->assertDenied($this->vote($voter, $troupeChef, $this->brigade, CRUD::UPDATE));
        $this->assertDenied($this->vote($voter, $troupeChef, $this->other, CRUD::UPDATE));
    }

    public function test_ended_attribution_grants_nothing(): void
    {
        $voter  = new GroupeVoter($this->fichierConfig());
        $membre = $this->membre(600);
        $this->attribute($membre, $this->troupe, $this->fonctionWith('ROLE_READ'), active: false);

        $this->assertDenied($this->vote($voter, $this->user(membre: $membre), $this->troupe, CRUD::READ));
    }

    public function test_user_without_membre_or_autorisation_is_denied(): void
    {
        $voter = new GroupeVoter($this->fichierConfig());

        $this->assertDenied($this->vote($voter, $this->user(['ROLE_USER']), $this->other, CRUD::READ));
    }

    public function test_autorisation_on_a_groupe_grants_its_roles(): void
    {
        $voter = new GroupeVoter($this->fichierConfig());
        $user  = $this->user(['ROLE_USER']);
        $autorisation = new Autorisation();
        $autorisation->setGroupe($this->brigade);
        $autorisation->setUser($user);
        $autorisation->setRoles(new ArrayCollection([$this->role('ROLE_DELETE')]));
        $user->addAutorisation($autorisation);

        $this->assertGranted($this->vote($voter, $user, $this->troupe, CRUD::DELETE));
        $this->assertDenied($this->vote($voter, $user, $this->troupe, CRUD::UPDATE));
        $this->assertDenied($this->vote($voter, $user, $this->other, CRUD::DELETE));
    }

    /**
     * Operations used to be matched with strpos(), so any role whose name
     * merely contains CREATE granted CREATE on the groupe.
     */
    public function test_role_merely_containing_the_operation_name_grants_nothing(): void
    {
        $voter = new GroupeVoter($this->fichierConfig());
        $chef  = $this->chefOf($this->troupe, 'ROLE_CREATE_ATTRIBUTION');

        $this->assertDenied($this->vote($voter, $chef, $this->troupe, CRUD::CREATE));
    }

    public function test_everywhere_role_on_a_fonction_still_covers_the_groupe(): void
    {
        $voter = new GroupeVoter($this->fichierConfig());
        $chef  = $this->chefOf($this->troupe, 'ROLE_READ_EVERYWHERE');

        $this->assertGranted($this->vote($voter, $chef, $this->other, CRUD::READ));
    }

    // --- MembreFamilleVoter ------------------------------------------------

    public function test_own_membre_can_be_read_and_updated_but_not_deleted(): void
    {
        $voter  = new MembreFamilleVoter($this->fichierConfig());
        $membre = $this->membre(700);
        $membre->setFamille($this->famille(70));
        $user   = $this->user(['ROLE_USER'], $membre);

        $this->assertGranted($this->vote($voter, $user, $membre, CRUD::READ));
        $this->assertGranted($this->vote($voter, $user, $membre, CRUD::UPDATE));
        $this->assertDenied($this->vote($voter, $user, $membre, CRUD::DELETE));
        $this->assertGranted($this->vote($voter, $user, $membre->getFamille(), CRUD::READ));
        $this->assertDenied($this->vote($voter, $user, $membre->getFamille(), CRUD::DELETE));
    }

    public function test_a_temporary_password_denies_even_the_own_membre(): void
    {
        $voter  = new MembreFamilleVoter($this->fichierConfig());
        $membre = $this->membre(703);
        $membre->setFamille($this->famille(73));
        $user   = $this->user(['ROLE_USER'], $membre);
        $user->setNewPasswordRequired(true);

        $this->assertDenied($this->vote($voter, $user, $membre, CRUD::READ));
        $this->assertDenied($this->vote($voter, $user, $membre->getFamille(), CRUD::READ));
    }

    public function test_user_whose_membre_has_no_famille_is_denied_on_other_familles(): void
    {
        $voter = new MembreFamilleVoter($this->fichierConfig());
        $user  = $this->user(['ROLE_USER'], $this->membre(701));

        $this->assertDenied($this->vote($voter, $user, $this->famille(71), CRUD::READ));
    }

    public function test_groupe_rights_reach_the_membres_their_famille_and_geniteurs(): void
    {
        $voter   = new MembreFamilleVoter($this->fichierConfig());
        $scout   = $this->membreIn($this->troupe, 702);
        $famille = $this->famille(72);
        $scout->setFamille($famille);
        $famille->addMembre($scout);
        $geniteur = new Geniteur();
        $geniteur->setFamille($famille);

        $chef = $this->chefOf($this->brigade, 'ROLE_UPDATE');
        $this->assertGranted($this->vote($voter, $chef, $scout, CRUD::UPDATE));
        $this->assertGranted($this->vote($voter, $chef, $famille, CRUD::UPDATE));
        $this->assertGranted($this->vote($voter, $chef, $geniteur, CRUD::UPDATE));

        $stranger = $this->chefOf($this->other, 'ROLE_UPDATE');
        $this->assertDenied($this->vote($voter, $stranger, $scout, CRUD::UPDATE));
        $this->assertDenied($this->vote($voter, $stranger, $famille, CRUD::UPDATE));
    }

    public function test_sg_may_delete_any_membre(): void
    {
        $voter = new MembreFamilleVoter($this->fichierConfig());

        $this->assertGranted($this->vote($voter, $this->user(['ROLE_SG']), $this->membre(703), CRUD::DELETE));
    }

    // --- ContactVoter ------------------------------------------------------

    public function test_contact_follows_the_rights_on_its_owner(): void
    {
        $scout   = $this->membreIn($this->troupe, 704);
        $adresse = new Adresse();
        $owners  = $this->createMock(ContactManager::class);
        $owners->method('findOwner')->willReturn($scout);
        $voter   = new ContactVoter($this->fichierConfig(), $owners);

        $this->assertGranted($this->vote($voter, $this->chefOf($this->troupe, 'ROLE_UPDATE'), $adresse, CRUD::UPDATE));
        $this->assertDenied($this->vote($voter, $this->chefOf($this->other, 'ROLE_UPDATE'), $adresse, CRUD::UPDATE));
        $this->assertGranted($this->vote($voter, $this->user(['ROLE_USER'], $scout), $adresse, CRUD::UPDATE));
    }

    public function test_orphan_contact_is_denied(): void
    {
        $owners = $this->createMock(ContactManager::class);
        $owners->method('findOwner')->willReturn(null);
        $voter  = new ContactVoter($this->fichierConfig(), $owners);

        $this->assertDenied($this->vote($voter, $this->chefOf($this->troupe, 'ROLE_READ'), new Adresse(), CRUD::READ));
    }

    // --- Attribution / distinction / structure ------------------------------

    /** @dataProvider sgOnlyWriteSubjects */
    public function test_anyone_reads_only_sg_writes(string $voterClass, \Closure $subject): void
    {
        $voter   = new $voterClass($this->fichierConfig());
        $subject = $subject();
        $user    = $this->user(['ROLE_USER']);

        $this->assertGranted($this->vote($voter, $user, $subject, CRUD::READ));
        $this->assertDenied($this->vote($voter, $user, $subject, CRUD::UPDATE));
        $this->assertDenied($this->vote($voter, $this->chefOf($this->troupe, 'ROLE_UPDATE'), $subject, CRUD::UPDATE));
        $this->assertGranted($this->vote($voter, $this->user(['ROLE_SG']), $subject, CRUD::DELETE));
    }

    public static function sgOnlyWriteSubjects(): iterable
    {
        yield 'attribution' => [AttributionVoter::class, fn () => new Attribution()];
        yield 'obtention distinction' => [ObtentionDistinctionVoter::class, fn () => new ObtentionDistinction()];
    }

    public function test_structure_is_sg_only(): void
    {
        $voter = new StructureVoter($this->fichierConfig());

        $this->assertDenied($this->vote($voter, $this->user(['ROLE_USER']), new Distinction(), CRUD::READ));
        $this->assertGranted($this->vote($voter, $this->user(['ROLE_SG']), new Distinction(), CRUD::UPDATE));
    }

    private function famille(int $id): Famille
    {
        $famille = new Famille();
        $this->setEntityId($famille, $id);

        return $famille;
    }
}
