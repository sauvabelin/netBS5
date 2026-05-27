<?php

namespace NetBS\SecureBundle\Entity;

use App\Entity\BSUser;
use Doctrine\ORM\Mapping as ORM;
use NetBS\SecureBundle\Repository\ResetPasswordRequestRepository;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestTrait;

#[ORM\Entity(repositoryClass: ResetPasswordRequestRepository::class)]
#[ORM\Table(name: 'sauvabelin_netbs_reset_password_request')]
class ResetPasswordRequest implements ResetPasswordRequestInterface
{
    use ResetPasswordRequestTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: BSUser::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private BSUser $user;

    public function __construct(BSUser $user, \DateTimeInterface $expiresAt, string $selector, string $hashedToken)
    {
        $this->user = $user;
        $this->initialize($expiresAt, $selector, $hashedToken);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): BSUser
    {
        return $this->user;
    }
}
