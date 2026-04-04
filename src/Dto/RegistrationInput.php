<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\User;
use App\Enum\Civility;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueEntity(
    fields: ['email'],
    entityClass: User::class,
    message: 'Cet email est déjà associé à un autre compte.',
    errorPath: 'email'
)]
final class RegistrationInput
{
    #[Assert\NotBlank(message: 'Veuillez saisir votre e-mail.')]
    #[Assert\Email(message: 'Adresse e-mail invalide.')]
    #[Assert\Length(max: 180)]
    public string $email = '';

    #[Assert\NotBlank(message: 'Veuillez choisir une civilité.')]
    public ?Civility $civility = null;

    #[Assert\NotBlank(message: 'Entrer un mot de passe')]
    #[Assert\Length(
        min: 6,
        max: 4096,
        minMessage: 'Votre mot de passe doit contenir minimum {{ limit }} caractère',
    )]
    public string $password = '';
}
