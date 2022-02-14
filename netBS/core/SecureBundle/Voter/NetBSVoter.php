<?php

namespace NetBS\SecureBundle\Voter;

use Doctrine\Common\Util\ClassUtils;
use NetBS\SecureBundle\Mapping\BaseUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

abstract class NetBSVoter extends Voter
{
    /**
     * Returns the class name(s) of the objects checked in this voter
     * @return string|array
     */
    abstract protected function supportClass();

    /**
     * Accept or denies the given crud operation on the given subject for the given user
     * @param string $operation a CRUD operation
     * @param \Object $subject
     * @param BaseUser $user
     * @return bool
     */
    abstract protected function accept($operation, $subject, BaseUser $user);

    /**
     * Determines if the attribute and subject are supported by this voter.
     *
     * @param string $attribute An attribute
     * @param mixed $subject The subject to secure, e.g. an object the user wants to access or any other PHP type
     *
     * @return bool True if the attribute and subject are supported, false otherwise
     */
    protected function supports($attribute, $subject)
    {
        return in_array($attribute, CRUD::toArray()) &&
            (is_string($this->supportClass())
                ? $this->supportClass() === ClassUtils::getRealClass(get_class($subject))
                : in_array(ClassUtils::getRealClass(get_class($subject)), $this->supportClass()));
    }

    /**
     * Perform a single access check operation on a given attribute, subject and token.
     * It is safe to assume that $attribute and $subject already passed the "supports()" method check.
     *
     * @param string $attribute
     * @param mixed $subject
     * @param TokenInterface $token
     *
     * @return bool
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user   = $token->getUser();

        if(!$user instanceof BaseUser)
            return false;

        if ($user->hasRole("ROLE_" . strtoupper($attribute) . "_EVERYWHERE"))
            return true;

        if($this->specialRule($user))
            return true;

        return $this->accept($attribute, $subject, $user);
    }

    /**
     * Allows to chain a special rule
     * @param BaseUser $user
     * @return true|false
     */
    protected function specialRule(BaseUser $user) {

        return $user->hasRole('ROLE_ADMIN');
    }
}
