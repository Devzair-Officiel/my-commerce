<?php

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Entity\Setting;
use App\Storefront\StorefrontGlobalsProvider;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur EasyAdmin pour la gestion des paramètres globaux du site (nom, logo, contact, réseaux sociaux).
 * Accessible uniquement aux super administrateurs.
 */
#[IsGranted('ROLE_SUPER_ADMIN')]
final class SettingCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly StorefrontGlobalsProvider $globalsProvider,
        private readonly RequestStack $requestStack,
    ) {}

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
            ->setColumns(5)
            ->setFormTypeOption('attr', ['maxlength' => 10]);

        $taxRate = IntegerField::new('taxe_rate', 'TVA (%)')
            ->setHelp('Ex: 20 pour 20%')
            ->setColumns(5);

        $description = TextareaField::new('description', 'Description')
            ->setColumns(12)
            ->setFormTypeOption('attr', ['rows' => 4]);

        $copyright = TextField::new("copyright", "Droits d'auteur")
            ->setColumns(4)
            ->setHelp('Ex: © 2025 Nidemiel — Tous droits réservés');

        $copyrightUrl = UrlField::new("copyrightUrl", "Lien droits d'auteur")
            ->setColumns(4)
            ->setHelp('URL vers laquelle pointe le texte de copyright (ex: page mentions légales, site officiel…)')
            ->setRequired(false);


        $logo = ImageField::new('logoMedia.filename', 'Image')
            ->setBasePath('/assets/images/setting')
            ->setUploadDir('public/assets/images/setting')
            ->setUploadedFileNamePattern('[slug].[timestamp].[extension]')
            ->setRequired(false);

        $logoAlt = TextField::new('logoAlt', 'Texte alternatif (alt)')
            ->setHelp("Décrit l'image pour l'accessibilité et le SEO")
            ->setRequired(false);

        $favicon = ImageField::new('faviconFilename', 'Favicon')
            ->setBasePath('/assets/images/setting')
            ->setUploadDir('public/assets/images/setting')
            ->setUploadedFileNamePattern('favicon.[timestamp].[extension]')
            ->setHelp("Formats recommandés : PNG 32×32 ou ICO. Affiché dans l'onglet du navigateur.")
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
            $copyrightUrl,
            $currency,
            $taxRate,
            $description,

            FormField::addFieldset('Image de marque')->setIcon('fa fa-image'),
            $logo,
            $logoAlt,
            $favicon,

            FormField::addTab('Adresse')->setIcon('fa fa-map-marker-alt'),
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

            FormField::addTab('Vidéo hero')->setIcon('fa fa-video'),
            FormField::addFieldset('Contenu de la bannière')->setIcon('fa fa-align-left')
                ->setHelp('Titre et bouton affichés par-dessus la vidéo (ou le carousel). Laisser vide pour utiliser les valeurs par défaut.'),
            TextField::new('heroTitle', 'Titre')
                ->setColumns(8)
                ->setRequired(false)
                ->setHelp('Ex : Acheter du miel naturel en ligne')
                ->setFormTypeOption('attr', ['maxlength' => 255, 'placeholder' => 'Acheter du miel naturel en ligne']),
            TextField::new('heroButtonText', 'Texte du bouton')
                ->setColumns(4)
                ->setRequired(false)
                ->setHelp('Ex : Découvrir')
                ->setFormTypeOption('attr', ['maxlength' => 100, 'placeholder' => 'Découvrir']),
            TextField::new('heroButtonUrl', 'Lien du bouton')
                ->setColumns(12)
                ->setRequired(false)
                ->setHelp('Ex : /boutique')
                ->setFormTypeOption('attr', ['maxlength' => 512, 'placeholder' => '/boutique']),

            FormField::addFieldset('Vidéo de la page d\'accueil')->setIcon('fa fa-film')
                ->setHelp('Si une vidéo est définie, elle remplace le carousel d\'images sur la page d\'accueil.'),
            TextField::new('heroVideoFilename', 'Vidéo actuelle')
                ->setColumns(12)
                ->onlyOnDetail()
                ->formatValue(static fn(?string $v) => $v
                    ? sprintf('<video src="/assets/videos/hero/%s" controls style="max-width:400px;max-height:200px;"></video>', htmlspecialchars($v))
                    : 'Aucune vidéo — le carousel d\'images est affiché.'
                )
                ->renderAsHtml(),
            TextField::new('heroVideoFilename', 'Vidéo actuelle')
                ->setColumns(12)
                ->onlyOnIndex()
                ->formatValue(static fn(?string $v) => $v ?: '—'),
            Field::new('_heroVideoUpload', 'Uploader une vidéo (MP4, WebM)')
                ->setColumns(8)
                ->onlyOnForms()
                ->setFormType(FileType::class)
                ->setFormTypeOptions([
                    'mapped'   => false,
                    'required' => false,
                    'attr'     => ['accept' => 'video/mp4,video/webm,video/*'],
                ])
                ->setHelp('Laisser vide pour conserver la vidéo actuelle.'),
            Field::new('_heroVideoDelete', 'Supprimer la vidéo')
                ->setColumns(4)
                ->onlyOnForms()
                ->setFormType(\Symfony\Component\Form\Extension\Core\Type\CheckboxType::class)
                ->setFormTypeOptions(['mapped' => false, 'required' => false])
                ->setHelp('Cocher pour revenir au carousel.'),

            FormField::addFieldset('Image poster (miniature affichée avant le chargement de la vidéo)')->setIcon('fa fa-image')
                ->setHelp('Recommandé : image JPG ou WebP au format 16/9, même dimensions que la vidéo. Améliore le LCP Lighthouse.'),
            TextField::new('heroPosterFilename', 'Poster actuel')
                ->setColumns(12)
                ->onlyOnDetail()
                ->formatValue(static fn(?string $v) => $v
                    ? sprintf('<img src="/assets/images/hero/%s" style="max-width:400px;max-height:200px;object-fit:cover;">', htmlspecialchars($v))
                    : 'Aucun poster défini.'
                )
                ->renderAsHtml(),
            TextField::new('heroPosterFilename', 'Poster actuel')
                ->setColumns(12)
                ->onlyOnIndex()
                ->formatValue(static fn(?string $v) => $v ?: '—'),
            Field::new('_heroPosterUpload', 'Uploader un poster (JPG, WebP, PNG)')
                ->setColumns(8)
                ->onlyOnForms()
                ->setFormType(FileType::class)
                ->setFormTypeOptions([
                    'mapped'   => false,
                    'required' => false,
                    'attr'     => ['accept' => 'image/jpeg,image/webp,image/png,image/*'],
                ])
                ->setHelp('Laisser vide pour conserver le poster actuel.'),
            Field::new('_heroPosterDelete', 'Supprimer le poster')
                ->setColumns(4)
                ->onlyOnForms()
                ->setFormType(\Symfony\Component\Form\Extension\Core\Type\CheckboxType::class)
                ->setFormTypeOptions(['mapped' => false, 'required' => false])
                ->setHelp('Cocher pour supprimer le poster.'),
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

    private function handleHeroPosterUpload(Setting $setting): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        // Suppression demandée via la case à cocher
        $post = $request->request->all();
        $delete = ($post['Setting']['_heroPosterDelete'] ?? $post['setting']['_heroPosterDelete'] ?? false);
        if ($delete) {
            $this->deleteHeroFile($setting->getHeroPosterFilename(), 'images/hero');
            $setting->setHeroPosterFilename(null);
            return;
        }

        $all  = $request->files->all();
        $file = $all['Setting']['_heroPosterUpload']
            ?? $all['setting']['_heroPosterUpload']
            ?? null;

        if (!$file) {
            return;
        }

        $uploadDir = \dirname(__DIR__, 3) . '/public/assets/images/hero';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Supprimer l'ancien fichier avant de sauvegarder le nouveau
        $this->deleteHeroFile($setting->getHeroPosterFilename(), 'images/hero');

        $filename = 'hero-poster-' . time() . '.' . $file->getClientOriginalExtension();
        $file->move($uploadDir, $filename);
        $setting->setHeroPosterFilename($filename);
    }

    private function handleHeroVideoUpload(Setting $setting): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        // Suppression demandée via la case à cocher
        $post = $request->request->all();
        $delete = ($post['Setting']['_heroVideoDelete'] ?? $post['setting']['_heroVideoDelete'] ?? false);
        if ($delete) {
            $this->deleteHeroFile($setting->getHeroVideoFilename(), 'videos/hero');
            $setting->setHeroVideoFilename(null);
            return;
        }

        $all  = $request->files->all();
        $file = $all['Setting']['_heroVideoUpload']
            ?? $all['setting']['_heroVideoUpload']
            ?? null;

        if (!$file) {
            return;
        }

        $uploadDir = \dirname(__DIR__, 3) . '/public/assets/videos/hero';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Supprimer l'ancien fichier avant de sauvegarder le nouveau
        $this->deleteHeroFile($setting->getHeroVideoFilename(), 'videos/hero');

        $filename = 'hero-video-' . time() . '.' . $file->getClientOriginalExtension();
        $file->move($uploadDir, $filename);
        $setting->setHeroVideoFilename($filename);
    }

    private function deleteHeroFile(?string $filename, string $subDir): void
    {
        if (!$filename) {
            return;
        }
        $path = \dirname(__DIR__, 3) . '/public/assets/' . $subDir . '/' . $filename;
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var Setting $setting */
        $setting = $entityInstance;

        if ($setting->getlogoMedia() === null) {
            $setting->setlogoMedia(new Media());
        }

        $this->handleHeroVideoUpload($setting);
        $this->handleHeroPosterUpload($setting);
        parent::persistEntity($entityManager, $entityInstance);
        $this->globalsProvider->invalidateSetting();
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $instance = $this->getContext()->getEntity()->getInstance();
        $this->handleHeroVideoUpload($instance);
        $this->handleHeroPosterUpload($instance);
        parent::updateEntity($entityManager, $entityInstance);
        $this->globalsProvider->invalidateSetting();
    }
}
