<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\User;
use App\Enum\Civility;
use Symfony\Component\Validator\Constraints as Assert;

final class EditUserInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $lastname = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $firstname = '';

    #[Assert\NotBlank(message: 'Veuillez choisir une civilité.')]
    public ?Civility $civility = null;

    #[Assert\NotBlank]
    #[Assert\Email(message: 'Adresse e-mail invalide.')]
    #[Assert\Length(max: 180)]
    public string $email = '';

    #[Assert\Length(max: 30)]
    public ?string $phone = null;

    public static function fromUser(User $user): self
    {
        $input = new self();
        $input->lastname = (string) $user->getLastname();
        $input->firstname = (string) $user->getFirstname();
        $input->civility = $user->getCivility();
        $input->email = (string) $user->getEmail();
        $input->phone = $user->getPhone();

        return $input;
    }
}
