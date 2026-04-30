/**
 * Tours guidés Shepherd.js pour le back-office.
 * Un tour par page clé — se déclenche automatiquement à la première visite.
 * Peut être relancé manuellement via resetAndStartTour().
 */
(function () {
    if (typeof Shepherd === 'undefined') return;

    /* ── Thème commun ── */
    const defaultStepOptions = {
        cancelIcon: { enabled: true },
        scrollTo: { behavior: 'smooth', block: 'center' },
        modalOverlayOpeningRadius: 8,
        popperOptions: {
            modifiers: [{ name: 'offset', options: { offset: [0, 14] } }],
        },
    };

    function btn(label, action, cls) {
        return { text: label, action, classes: cls ?? 'shepherd-btn-primary' };
    }
    const next = (t) => btn('Suivant →', t.next.bind(t));
    const back = (t) => btn('← Retour', t.back.bind(t), 'shepherd-btn-secondary');
    const done = (t) => btn('Terminer ✓', t.complete.bind(t));

    /* ── Scroll vers le haut d'un élément sans le centrer dans le viewport ── */
    function scrollToTop(selector) {
        const el = document.querySelector(selector);
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    /* ── Détection de la page courante ── */
    function currentPage() {
        const url = window.location.href;
        if (/\/admin\/guide/.test(url)) return 'guide';
        const params = new URLSearchParams(window.location.search);
        const ctrl = params.get('crudControllerFqcn') ?? '';
        const action = params.get('crudAction') ?? '';
        if (/DashboardController/.test(url) || url.endsWith('/admin')) return 'dashboard';
        if (/OrderCrudController/.test(ctrl) && action === 'detail') return 'order_detail';
        if (/OrderCrudController/.test(ctrl)) return 'orders';
        if (/ProductCrudController/.test(ctrl)) return 'products';
        return null;
    }

    /* ── Clés localStorage ── */
    const KEYS = {
        dashboard: 'ea_tour_dashboard_v1',
        orders: 'ea_tour_orders_v1',
        order_detail: 'ea_tour_order_detail_v1',
        products: 'ea_tour_products_v1',
    };

    function isDone(page) { return localStorage.getItem(KEYS[page]) === '1'; }
    function markDone(page) { if (KEYS[page]) localStorage.setItem(KEYS[page], '1'); }

    window.resetAllTours = function () {
        Object.values(KEYS).forEach(k => localStorage.removeItem(k));
    };

    /* ══════════════════════════════════════════════════
       TOUR — DASHBOARD
    ══════════════════════════════════════════════════ */
    function buildDashboardTour() {
        const t = new Shepherd.Tour({
            useModalOverlay: true,
            defaultStepOptions,
            keyboardNavigation: true,
        });

        t.on('complete', () => markDone('dashboard'));
        t.on('cancel',   () => markDone('dashboard'));

        t.addSteps([
            {
                id: 'welcome',
                text: '<strong>Bienvenue dans votre administration ! 👋</strong><br><br>Je vais vous faire découvrir les différentes zones de votre tableau de bord. Des bulles comme celle-ci apparaîtront à côté de chaque zone pour vous expliquer à quoi elle sert.<br><br>Utilisez les boutons <b>Suivant →</b> et <b>← Retour</b> pour naviguer, ou cliquez <b>✕</b> pour arrêter à tout moment.',
                buttons: [btn('Passer le tour', t.cancel.bind(t), 'shepherd-btn-secondary'), next(t)],
            },
            {
                id: 'sidebar-brand',
                attachTo: { element: '.sidebar-nav, .nav-sidebar, .main-sidebar nav, #sidebar-menu, aside.main-sidebar', on: 'right' },
                scrollTo: false,
                text: '<strong>Le menu de navigation 📋</strong><br><br>Le panneau sur votre gauche est votre point de départ. Il est organisé en rubriques :<br><br>• <b>Catalogue</b> — créer et gérer vos produits<br>• <b>Vente</b> — commandes, expéditions, factures<br>• <b>Avis</b> — valider les avis clients avant publication<br>• <b>Contenu</b> — pages, blog, FAQ de la boutique<br>• <b>Configuration</b> — les réglages de votre boutique<br><br>Cliquez sur n\'importe quelle rubrique pour y accéder directement.',
                buttons: [back(t), next(t)],
                when: { show() { window.scrollTo({ top: 0, behavior: 'smooth' }); } },
            },
            {
                id: 'dash-alert',
                attachTo: { element: '.dash-alert', on: 'bottom' },
                text: '<strong>Alertes urgentes 🚨</strong><br><br>Ce bandeau apparaît uniquement quand il y a quelque chose d\'important à traiter — par exemple des commandes payées qui attendent d\'être expédiées, ou des produits dont le stock est épuisé.<br><br>Quand tout va bien, ce bandeau disparaît automatiquement.',
                buttons: [back(t), next(t)],
                canClickTarget: false,
            },
            {
                id: 'kpis',
                attachTo: { element: '.row.g-3.mb-4:first-of-type, .kpi2', on: 'bottom' },
                text: '<strong>Vos indicateurs clés 📊</strong><br><br>Ces 4 cartes vous donnent un aperçu instantané de l\'activité de votre boutique :<br><br>• <b>Commandes totales</b> — le nombre total de commandes reçues<br>• <b>Payées (30 j)</b> — combien de commandes ont été réglées ce mois<br>• <b>CA payé (30 j)</b> — votre chiffre d\'affaires du mois<br>• <b>Clients</b> — le nombre de clients inscrits<br><br>La flèche ↑ ou ↓ compare avec le mois précédent.',
                buttons: [back(t), next(t)],
                canClickTarget: false,
            },
            {
                id: 'chart',
                attachTo: { element: '#revenueChart, canvas', on: 'top' },
                text: '<strong>Le graphique des ventes 📈</strong><br><br>Ce graphique affiche l\'évolution de vos ventes sur les 14 derniers jours. Chaque barre représente un jour. C\'est utile pour repérer d\'un coup d\'œil si une journée a été plus active que les autres.',
                buttons: [back(t), next(t)],
                canClickTarget: false,
            },
            {
                id: 'recent-orders',
                attachTo: { element: '.order-row, table.table', on: 'top' },
                text: '<strong>Les dernières commandes 🛒</strong><br><br>Ce tableau liste les 10 commandes les plus récentes. Pour chaque commande vous voyez :<br>• La référence et le montant<br>• Le statut du paiement (payée, en attente…)<br>• Le statut de livraison (en préparation, expédiée…)<br>• La date de paiement<br><br>Cliquez sur une ligne pour ouvrir le détail complet de la commande.',
                buttons: [back(t), next(t)],
                canClickTarget: false,
            },
            {
                id: 'low-stock',
                attachTo: { element: '.stock-list, .stock-row', on: 'top' },
                text: '<strong>Produits en stock bas ⚠️</strong><br><br>Cette liste vous montre les produits dont il reste peu de stock (5 unités ou moins) ou qui sont en rupture. C\'est un rappel visuel pour que vous pensiez à réapprovisionner avant d\'être en rupture complète.',
                buttons: [back(t), next(t)],
                canClickTarget: false,
            },
            {
                id: 'guide-link',
                attachTo: { element: 'a[href*="admin-guide"], a[href*="admin_guide"]', on: 'right' },
                scrollTo: false,
                text: '<strong>Le guide d\'utilisation 📖</strong><br><br>Ce lien en bas du menu vous ramène ici à tout moment. Vous y trouverez des explications détaillées sur toutes les fonctionnalités de l\'administration.<br><br>C\'est tout pour ce tour ! Bonne gestion de votre boutique. 🎉',
                buttons: [back(t), done(t)],
                when: { show() {
                    const el = document.querySelector('a[href*="admin-guide"], a[href*="admin_guide"]');
                    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } },
            },
        ]);

        return t;
    }

    /* ══════════════════════════════════════════════════
       TOUR — LISTE DES COMMANDES
    ══════════════════════════════════════════════════ */
    function buildOrdersTour() {
        const t = new Shepherd.Tour({
            useModalOverlay: true,
            defaultStepOptions,
            keyboardNavigation: true,
        });

        t.on('complete', () => markDone('orders'));
        t.on('cancel',   () => markDone('orders'));

        t.addSteps([
            {
                id: 'orders-intro',
                text: '<strong>La liste de vos commandes 🛒</strong><br><br>Voici toutes les commandes passées sur votre boutique, les plus récentes en premier. C\'est ici que vous gérez tout : voir ce qui a été commandé, expédier les colis, rembourser un client.',
                buttons: [btn('Passer le tour', t.cancel.bind(t), 'shepherd-btn-secondary'), next(t)],
            },
            {
                id: 'search',
                attachTo: { element: '.content-search, [data-ea-search], form[role="search"]', on: 'bottom' },
                text: '<strong>La barre de recherche 🔍</strong><br><br>Vous cherchez une commande précise ? Tapez le nom du client, son email, la référence de la commande ou le numéro de transaction. La liste se filtre instantanément.',
                buttons: [back(t), next(t)],
            },
            {
                id: 'filters',
                attachTo: { element: '.datagrid-filters, [data-ea-filters], .filters-form', on: 'bottom' },
                text: '<strong>Les filtres 🎯</strong><br><br>Très utile au quotidien ! Par exemple : afficher uniquement les commandes <em>payées mais pas encore expédiées</em>, ou filtrer par date pour voir les commandes de la semaine.',
                buttons: [back(t), next(t)],
            },
            {
                id: 'table',
                attachTo: { element: 'table.datagrid, .datagrid-table', on: 'top' },
                text: '<strong>Le tableau des commandes 📋</strong><br><br>Chaque ligne est une commande. Les petits badges colorés indiquent l\'état du paiement et de la livraison. Un badge <b style="color:#bbf7d0;">vert</b> = tout va bien. Un badge <b style="color:#fde68a;">orange</b> = action à faire de votre côté.',
                buttons: [back(t), next(t)],
                canClickTarget: false,
            },
            {
                id: 'actions',
                attachTo: { element: 'td.actions, .datagrid-row-actions', on: 'left' },
                text: '<strong>Les boutons d\'action ⚡</strong><br><br>Sans avoir à ouvrir la commande, vous pouvez directement :<br>• 👁 <b>Voir</b> le détail complet<br>• 🚚 <b>Expédier</b> (génère l\'étiquette et prévient le client)<br>• 💰 <b>Rembourser</b> si besoin',
                buttons: [back(t), done(t)],
                canClickTarget: false,
            },
        ]);

        return t;
    }

    /* ══════════════════════════════════════════════════
       TOUR — DÉTAIL D'UNE COMMANDE
    ══════════════════════════════════════════════════ */
    function buildOrderDetailTour() {
        const t = new Shepherd.Tour({
            useModalOverlay: true,
            defaultStepOptions,
            keyboardNavigation: true,
        });

        t.on('complete', () => markDone('order_detail'));
        t.on('cancel',   () => markDone('order_detail'));

        t.addSteps([
            {
                id: 'detail-intro',
                text: '<strong>La fiche complète d\'une commande 📋</strong><br><br>Cette page vous donne toutes les informations sur une commande en particulier : ce que le client a commandé, où livrer, comment il a payé, et les actions que vous pouvez effectuer.',
                buttons: [btn('Passer le tour', t.cancel.bind(t), 'shepherd-btn-secondary'), next(t)],
            },
            {
                id: 'action-buttons',
                attachTo: { element: '.page-actions, .content-header-actions', on: 'bottom' },
                text: '<strong>Les boutons d\'action en haut à droite 🎬</strong><br><br>C\'est ici que vous agissez sur la commande :<br>• <b>Expédier</b> — à cliquer quand le colis est prêt à partir. Génère l\'étiquette et envoie un email au client.<br>• <b>Rembourser</b> — rembourse le client directement sur sa carte bancaire.<br>• <b>Modifier</b> — pour corriger un statut si besoin.',
                buttons: [back(t), next(t)],
                canClickTarget: false,
            },
            {
                id: 'tabs',
                attachTo: { element: '.nav-tabs, [role="tablist"]', on: 'bottom' },
                text: '<strong>Les onglets — tout est organisé 📂</strong><br><br>Les informations sont regroupées par thème pour ne pas surcharger l\'écran :<br>• <b>Articles</b> — ce que le client a commandé<br>• <b>Statuts</b> — état du paiement et de la livraison<br>• <b>Adresses</b> — où livrer et l\'adresse de facturation<br>• <b>Transport & paiement</b> — le transporteur choisi et la référence bancaire',
                buttons: [back(t), next(t)],
                canClickTarget: false,
            },
            {
                id: 'tab-articles',
                attachTo: { element: '.nav-tabs .nav-item:first-child, [role="tablist"] li:first-child', on: 'bottom' },
                text: '<strong>L\'onglet "Articles" — le récapitulatif de la commande 🧾</strong><br><br>Vous y trouvez la liste de chaque produit commandé avec l\'image, le nom, la quantité et le prix. En bas, le total HT, la TVA, les frais de port et le <b>total TTC</b> que le client a payé.',
                buttons: [back(t), done(t)],
                canClickTarget: false,
            },
        ]);

        return t;
    }

    /* ══════════════════════════════════════════════════
       TOUR — PRODUITS
    ══════════════════════════════════════════════════ */
    function buildProductsTour() {
        const t = new Shepherd.Tour({
            useModalOverlay: true,
            defaultStepOptions,
            keyboardNavigation: true,
        });

        t.on('complete', () => markDone('products'));
        t.on('cancel',   () => markDone('products'));

        t.addSteps([
            {
                id: 'products-intro',
                text: '<strong>Votre catalogue de produits 📦</strong><br><br>C\'est ici que vous gérez tous vos produits : ajouter de nouveaux articles, modifier les prix, mettre à jour le stock ou ajouter des photos.',
                buttons: [btn('Passer le tour', t.cancel.bind(t), 'shepherd-btn-secondary'), next(t)],
            },
            {
                id: 'new-product',
                attachTo: { element: 'a[href*="crudAction=new"], .btn-primary[href*="new"]', on: 'bottom' },
                text: '<strong>Ajouter un nouveau produit ➕</strong><br><br>Cliquez ici pour créer un article. Le minimum à renseigner : un <b>titre</b>, un <b>prix TTC</b> et un <b>stock initial</b>. Tout le reste (description, catégorie, images) est facultatif mais recommandé pour une belle présentation en boutique.',
                buttons: [back(t), next(t)],
                canClickTarget: false,
            },
            {
                id: 'stock-column',
                attachTo: { element: 'th[data-column="stock"], td.field-integer', on: 'top' },
                text: '<strong>La colonne Stock 📊</strong><br><br>Vous voyez en un coup d\'œil le niveau de stock de chaque produit. Les badges colorés vous alertent :<br>• <b style="color:#fde68a;">Orange</b> = stock faible (5 unités ou moins)<br>• <b style="color:#fca5a5;">Rouge "Rupture"</b> = plus aucun stock, le produit est indisponible en boutique.<br><br>Le stock se gère tout seul après chaque vente ou remboursement.',
                buttons: [back(t), done(t)],
                canClickTarget: false,
            },
        ]);

        return t;
    }

    /* ── Helper : ne pas planter si l'élément est absent ── */
    function safeStart(tour) {
        // Pour les sélecteurs multiples (ex: '.a, .b'), tester chaque variante
        tour.steps.forEach(step => {
            const attachTo = step.options?.attachTo;
            if (!attachTo?.element) return;
            const selectors = attachTo.element.split(',').map(s => s.trim());
            const found = selectors.find(s => document.querySelector(s));
            if (found) {
                step.options.attachTo.element = found; // utilise le premier sélecteur qui existe
            } else {
                delete step.options.attachTo; // aucun élément trouvé → bulle flottante
            }
        });
        tour.start();
    }

    function highlightIfExists(selector) {
        document.querySelector(selector)?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    /* ── Démarrage automatique ── */
    function autoStart() {
        const page = currentPage();
        if (!page || isDone(page)) return;

        const builders = {
            dashboard: buildDashboardTour,
            orders: buildOrdersTour,
            order_detail: buildOrderDetailTour,
            products: buildProductsTour,
        };

        const builder = builders[page];
        if (!builder) return;

        // Petite pause pour que le DOM EasyAdmin soit complètement rendu
        setTimeout(() => safeStart(builder()), 600);
    }

    /* ── API publique pour le bouton "Relancer" de la page Guide ── */
    window.resetAndStartTour = function (page) {
        if (page) {
            localStorage.removeItem(KEYS[page]);
        } else {
            resetAllTours();
        }
        window.location.href = '/admin';
    };

    window.startTourNow = function (page) {
        const builders = {
            dashboard: buildDashboardTour,
            orders: buildOrdersTour,
            order_detail: buildOrderDetailTour,
            products: buildProductsTour,
        };
        const builder = builders[page];
        if (builder) safeStart(builder());
    };

    document.addEventListener('DOMContentLoaded', autoStart);
})();
