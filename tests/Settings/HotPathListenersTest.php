<?php

declare(strict_types=1);

namespace App\Tests\Settings;

use App\Entity\BSMembre;
use App\Listener\DoctrineMembreAdabsIdListener;
use App\Subscriber\DoctrineUserAccountSubscriber;
use App\Subscriber\OldFichierMapperSubscriber;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use NetBS\CoreBundle\Rule\RuleEvaluator;
use NetBS\CoreBundle\Rule\RuleRegistry;
use NetBS\CoreBundle\Service\ParameterManager;
use NetBS\CoreBundle\Settings\SettingDefinition;
use NetBS\CoreBundle\Settings\SettingNotConfiguredException;
use NetBS\CoreBundle\Settings\SettingType;
use NetBS\FichierBundle\Entity\Attribution;
use NetBS\FichierBundle\Entity\Fonction;
use NetBS\FichierBundle\Mapping\BaseMembre;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * These Doctrine listeners run on every membre load / save. A missing setting must not take
 * the whole application down: they log a warning and skip their work instead.
 */
final class HotPathListenersTest extends TestCase
{
    public function test_adabs_listener_skips_when_not_configured(): void
    {
        $membre = $this->createMock(BSMembre::class);
        $membre->expects($this->once())->method('_setAdabsId')->with(null);

        $listener = new DoctrineMembreAdabsIdListener($this->unconfiguredParams(), $this->warnedOnce());
        $listener->postLoad(new LifecycleEventArgs($membre, $this->createMock(EntityManager::class)));
    }

    public function test_adabs_listener_keeps_stored_id_when_reference_is_broken(): void
    {
        // BSMembre::setStatut() keeps the attribution whose groupe is _adabsId open; falling
        // back to null (0) would close the ADABS attribution of an unsubscribed member.
        $params = $this->unconfiguredParams();
        $params->method('getStoredValue')->willReturn('3');

        $membre = $this->createMock(BSMembre::class);
        $membre->expects($this->once())->method('_setAdabsId')->with('3');

        $listener = new DoctrineMembreAdabsIdListener($params, $this->warnedOnce());
        $listener->postLoad(new LifecycleEventArgs($membre, $this->createMock(EntityManager::class)));
    }

    public function test_user_account_subscriber_skips_when_not_configured(): void
    {
        $fonction = $this->createMock(Fonction::class);
        $fonction->method('getPoids')->willReturn(500);

        $membre = $this->createMock(BSMembre::class);
        $membre->method('getStatut')->willReturn(BaseMembre::INSCRIT);
        $membre->method('getActivesAttributions')->willReturn([]);

        $attribution = $this->createMock(Attribution::class);
        $attribution->method('getMembre')->willReturn($membre);
        $attribution->method('getFonction')->willReturn($fonction);

        $manager = $this->createMock(EntityManager::class);
        $manager->expects($this->never())->method('persist');

        $subscriber = new DoctrineUserAccountSubscriber(
            $this->createMock(UserPasswordHasherInterface::class),
            $this->createMock(MailerInterface::class),
            new RequestStack(),
            $this->unconfiguredParams(),
            $this->warnedOnce(),
            new RuleEvaluator(new RuleRegistry([], []), new NullLogger()),
        );
        $subscriber->postPersist(new LifecycleEventArgs($attribution, $manager));
    }

    public function test_old_fichier_mapper_skips_when_not_configured(): void
    {
        $membre = $this->createMock(BSMembre::class);
        $membre->expects($this->never())->method('getSendableAdresse');

        $subscriber = new OldFichierMapperSubscriber($this->unconfiguredParams(), $this->warnedOnce());
        $subscriber->postPersist(new LifecycleEventArgs($membre, $this->createMock(EntityManager::class)));
    }

    private function unconfiguredParams(): ParameterManager
    {
        $definition = new SettingDefinition('bs', 'groupe.adabs_id', SettingType::Entity, 'Groupe ADABS', 'brigade',
            important: true, target: 'groupe');

        $params = $this->createMock(ParameterManager::class);
        $params->method('getValue')->willThrowException(new SettingNotConfiguredException($definition));

        return $params;
    }

    private function warnedOnce(): LoggerInterface
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with($this->stringContains('Groupe ADABS'));

        return $logger;
    }
}
