<?php

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Entity\Setting;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

final class SettingCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Setting::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, Action::DELETE]);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Paramètres du site')
            ->setEntityLabelInPlural('Paramètres du site')
            ->setPageTitle(Crud::PAGE_INDEX, 'Paramètres du site')
            ->setPageTitle(Crud::PAGE_EDIT, 'Modifier les paramètres')
            ->setPageTitle(Crud::PAGE_NEW, 'Créer les paramètres')
            ->setDefaultSort(['id' => 'DESC'])
            ->showEntityActionsInlined()
            ->setSearchFields(['website_name', 'currency', 'city', 'phone']);
    }


    public function configureFields(string $pageName): iterable
    {
        $id = IdField::new('id')->onlyOnIndex();

        // --- Infos générales
        $websiteName = TextField::new('website_name', 'Nom du site')
            ->setColumns(3)
            ->setFormTypeOption('attr', ['maxlength' => 255]);

        $currency = TextField::new('currency', 'Devise')
            ->setHelp('Ex: EUR, USD')
            ->setColumns(3)
            ->setFormTypeOption('attr', ['maxlength' => 10]);

        $taxRate = IntegerField::new('taxe_rate', 'TVA (%)')
            ->setHelp('Ex: 20 pour 20%')
            ->setColumns(3);

        $description = TextareaField::new('description', 'Description')
            ->setColumns(12)
            ->setFormTypeOption('attr', ['rows' => 4]);

        $copyright = TextField::new("copyright", "Droits d'auteur")
            ->setColumns(3)
            ->setHelp('Ex: © 2020 - 2025 Agence web Hypedesign - Mentions Légales - CGV');


        $logo = ImageField::new('logoMedia.filename', 'Image')
            ->setBasePath('/assets/images/setting')
            ->setUploadDir('public/assets/images/setting')
            ->setUploadedFileNamePattern('[slug].[timestamp].[extension]')
            ->setRequired(false);

        $logoAlt = TextField::new('logoAlt', 'Texte alternatif (alt)')
            ->setHelp('Décrit l’image pour l’accessibilité et le SEO')
            ->setRequired(false);

        // --- Adresse
        $street = TextField::new('street', 'Rue')
            ->setColumns(12)
            ->setFormTypeOption('attr', ['maxlength' => 255]);

        $codePostal = TextField::new('code_postal', 'Code postal')
            ->setColumns(4)
            ->setFormTypeOption('attr', ['maxlength' => 20]);

        $city = TextField::new('city', 'Ville')
            ->setColumns(4)
            ->setFormTypeOption('attr', ['maxlength' => 255]);

        $state = TextField::new('state', 'Région / État')
            ->setColumns(4)
            ->setFormTypeOption('attr', ['maxlength' => 255]);

        // --- Contact
        $phone = TelephoneField::new('phone', 'Téléphone')
            ->setColumns(6)
            ->setFormTypeOption('attr', [
                'inputmode' => 'tel',
                'autocomplete' => 'tel',
                'maxlength' => 50,
            ]);

        $email = EmailField::new('email', 'Email')
            ->setColumns(6)
            ->setFormTypeOption('attr', ['maxlength' => 255]);

        $emailNoReply = EmailField::new('emailNoReply', 'Email No Reply')
            ->setColumns(6)
            ->setFormTypeOption('attr', ['maxlength' => 255]);

        // --- Social Link
        $facebook = TextField::new('facebookLink', 'Facebook')
            ->setHelp('<i class="fab fa-facebook-f"></i> URL complète (ex: https://facebook.com/ma-page)')
            ->setFormTypeOption('attr', [
                'placeholder' => 'https://facebook.com/ma-page',
                'inputmode' => 'url',
                'autocomplete' => 'url',
            ])
            ->setColumns(6);

        $insta = TextField::new('instaLink', 'Instagram')
            ->setHelp('<i class="fab fa-instagram"></i> URL complète (ex: https://instagram.com/mon-compte)')
            ->setFormTypeOption('attr', [
                'placeholder' => 'https://instagram.com/mon-compte',
                'inputmode' => 'url',
                'autocomplete' => 'url',
            ])
            ->setColumns(6);

        $youtube = TextField::new('youtubeLink', 'YouTube')
            ->setHelp('<i class="fab fa-youtube"></i> URL complète (ex: https://youtube.com/@ma-chaine)')
            ->setFormTypeOption('attr', [
                'placeholder' => 'https://youtube.com/@ma-chaine',
                'inputmode' => 'url',
                'autocomplete' => 'url',
            ])
            ->setColumns(12);

        // --- Index (liste)
        if (Crud::PAGE_INDEX === $pageName) {
            return [
                $id,
                TextField::new('website_name', 'Nom'),
                TextField::new('currency', 'Devise'),
                IntegerField::new('taxe_rate', 'TVA (%)'),
                TextField::new('city', 'Ville'),
                TextField::new('phone', 'Téléphone'),
                $logo->onlyOnIndex(),
            ];
        }

        // --- Form (edit/new/detail)
        return [
            FormField::addTab('Général')->setIcon('fa fa-gear'),
            FormField::addFieldset('Identité du site')->setIcon('fa fa-globe'),
            $websiteName,
            $copyright,
            $currency,
            $taxRate,
            $description,

            FormField::addFieldset('Image de marque')->setIcon('fa fa-image'),
            $logo,
            $logoAlt,

            FormField::addTab('Adresse')->setIcon('fa fa-location-dot'),
            FormField::addFieldset('Adresse postale')->setIcon('fa fa-map'),
            $street,
            $codePostal,
            $city,
            $state,

            FormField::addTab('Contact')->setIcon('fa fa-phone'),
            FormField::addFieldset('Coordonnées')->setIcon('fa fa-address-book'),
            $phone,
            $email,
            $emailNoReply,

            FormField::addTab('Réseaux sociaux')->setIcon('fa-solid fa-share-nodes'),
            FormField::addFieldset('Liens')->setIcon('fa-solid fa-link'),
            $facebook,
            $insta,
            $youtube,

            FormField::addTab('Facturation')->setIcon('fa fa-file-invoice'),
            FormField::addFieldset('Micro-entrepreneur')->setIcon('fa fa-user-tie'),
            TextField::new('ownerName', 'Nom du propriétaire')
                ->setHelp('Nom complet du micro-entrepreneur, apparaît sur les factures')
                ->setColumns(6),
            TextField::new('siret', 'SIRET')
                ->setHelp('14 chiffres sans espaces')
                ->setColumns(6),
            TextareaField::new('legalMentions', 'Mentions légales complémentaires')
                ->setHelp('Apparaît en bas de chaque facture (ex: conditions de paiement spécifiques)')
                ->setColumns(12)
                ->setFormTypeOption('attr', ['rows' => 4])
                ->setRequired(false),
        ];
    }

    public function createEntity(string $entityFqcn): Setting
    {
        /** @var Setting $setting */
        $setting = new Setting();

        // Important: créer le Media tout de suite pour éviter les null
        $media = new Media();
        $setting->setlogoMedia($media);

        return $setting;
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var Setting $setting */
        $setting = $entityInstance;

        // Sécurité: si jamais media est null, on le recrée
        if ($setting->getlogoMedia() === null) {
            $setting->setlogoMedia(new Media());
        }

        parent::persistEntity($entityManager, $entityInstance);
    }
    
}
