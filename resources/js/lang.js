const LANG_KEY = 'primegest_lang';

const messages = {
  fr: {
    // ── Dashboard ──────────────────────────────────────────────────
    dashboard: 'TABLEAU DE BORD',
    total_stock: 'Total des stocks',
    total_sales: 'Total des ventes',
    total_expenses: 'Total des dépenses',
    stock_alerts: 'Alertes stock',
    stock_alerts_low: 'Alertes stock bas',
    recent_activity: 'Activité récente',
    best_sales: 'Meilleures ventes',
    shortcuts: 'Raccourcis',
    stock_value: 'Valeur du stock',
    sales_recorded: 'Sorties enregistrées',
    cash_out: 'Sorties caisse',
    threshold: 'Seuil',
    no_activity: 'Aucune activité récente.',
    no_sales: 'Aucune vente enregistrée.',
    no_alerts: 'Aucune alerte.',

    // ── Navigation ─────────────────────────────────────────────────
    cashier: 'Caisse',
    stock_moves: 'Mouvement-stock',
    products: 'Produits',
    clients: 'Clients',
    suppliers: 'Fournisseurs',
    debts: 'Créances & dettes',
    journal: 'Journal',
    archives: 'Archives',
    users: 'Utilisateurs',
    settings: 'Paramètres',
    employees: 'Employés',
    payroll: 'Fiches de paie',
    report: 'Rapport',
    reports: 'Rapports',
    human_resources: 'Ressources humaines',

    // ── Graphiques ─────────────────────────────────────────────────
    sales_7d: 'Ventes 7 derniers jours',
    sales_purchases_monthly: 'Ventes & achats mensuels',
    sales_label: 'Ventes',
    purchases_label: 'Achats',
    chart_daily: 'Graphique journalier',
    chart_monthly: 'Graphique mensuel',
    chart_type: 'Afficher :',

    // ── Titres pages ───────────────────────────────────────────────
    archives_title: 'Archives',
    stock_moves_title: 'Mouvements de stock',
    cash_title: 'Caisse',

    // ── Actions ────────────────────────────────────────────────────
    view_report: 'Voir rapport',
    view_journal: 'Voir journal',
    add_product: 'Ajouter produit',
    new_sale: 'Nouvelle vente',
    save: 'Enregistrer',
    cancel: 'Annuler',
    edit: 'Modifier',
    delete: 'Supprimer',
    confirm: 'Confirmer',
    close: 'Fermer',
    search: 'Rechercher',
    add: 'Ajouter',
    validate: 'Valider',
    reject: 'Rejeter',
    back: 'Retour',
    download: 'Télécharger',
    print: 'Imprimer',
    export: 'Exporter',

    // ── Formulaires ────────────────────────────────────────────────
    name: 'Nom',
    email: 'Email',
    password: 'Mot de passe',
    password_confirm: 'Confirmer le mot de passe',
    admin_password: 'Mot de passe administrateur',
    role: 'Rôle',
    phone: 'Téléphone',
    address: 'Adresse',
    date: 'Date',
    amount: 'Montant',
    quantity: 'Quantité',
    product: 'Produit',
    description: 'Description',
    status: 'Statut',
    type: 'Type',
    total: 'Total',
    note: 'Note',

    // ── Erreurs ────────────────────────────────────────────────────
    error_password_incorrect: 'Mot de passe incorrect.',
    error_access_denied: 'Accès refusé. Vous n\'avez pas les permissions nécessaires.',
    error_access_restricted: 'Accès restreint à cette page.',
    error_not_found: 'Ressource introuvable.',
    error_server: 'Erreur serveur. Veuillez réessayer.',
    error_required: 'Ce champ est obligatoire.',
    error_email_invalid: 'Adresse email invalide.',
    error_email_taken: 'Cet email est déjà utilisé.',
    error_phone_taken: 'Ce numéro de téléphone est déjà utilisé.',
    error_insufficient_stock: 'Stock insuffisant pour ce produit.',
    error_invalid_amount: 'Montant invalide.',
    error_same_branch: 'La succursale de destination doit être différente.',
    error_transfer_processed: 'Ce transfert est déjà traité.',

    // ── Succès ─────────────────────────────────────────────────────
    success_saved: 'Enregistrement réussi.',
    success_deleted: 'Suppression réussie.',
    success_updated: 'Mise à jour réussie.',
    success_transfer_requested: 'Demande de transfert enregistrée.',
    success_transfer_validated: 'Transfert validé avec succès.',
    success_transfer_rejected: 'Transfert rejeté.',

    // ── Statuts ────────────────────────────────────────────────────
    status_pending: 'En attente',
    status_validated: 'Validé',
    status_rejected: 'Rejeté',
    status_paid: 'Payé',
    status_unpaid: 'Non payé',
    status_active: 'Actif',
    status_inactive: 'Inactif',

    // ── Caisse ─────────────────────────────────────────────────────
    cash_in: 'Entrée',
    cash_balance: 'Solde',
    initial_balance: 'Solde initial',
    operation_type: 'Type d\'opération',

    // ── Mouvement stock ────────────────────────────────────────────
    entry: 'Entrée',
    exit: 'Sortie',
    sale: 'Vente',
    purchase: 'Achat',
    payment_cash: 'Espèces',
    payment_credit: 'Crédit',
    payment_reduction: 'Réduction',
    available_stock: 'Stock disponible',
    unit_price: 'Prix unitaire',
    purchase_price: "Prix d'achat",
    sale_price: 'Prix de vente',
    stock_threshold: "Seuil d'alerte",

    // ── Transferts ─────────────────────────────────────────────────
    transfer: 'Transfert',
    transfer_cash: 'Transfert de caisse',
    transfer_stock: 'Transfert de stock',
    from_branch: 'De la succursale',
    to_branch: 'Vers la succursale',
    branch: 'Succursale',
    branches: 'Succursales',
    central_dashboard: 'Dashboard centralisé',

    // ── RH ─────────────────────────────────────────────────────────
    employee: 'Employé',
    position: 'Poste',
    salary: 'Salaire',
    hire_date: "Date d'embauche",
    payslip: 'Fiche de paie',

    // ── Factures ───────────────────────────────────────────────────
    invoice: 'Facture',
    invoice_number: 'Numéro de facture',
    invoice_date: 'Date de facture',
    client: 'Client',
    tax: 'TVA',
    subtotal: 'Sous-total HT',
    tax_amount: 'Montant TVA',
    total_ttc: 'Total TTC',

    // ── Connexion ──────────────────────────────────────────────────
    login: 'Connexion',
    logout: 'Déconnexion',
    company_name: "Nom de l'entreprise",
    remember_me: 'Se souvenir de moi',
    forgot_password: 'Mot de passe oublié ?',
    error_company_not_found: "Cette entreprise n'existe pas.",
    error_invalid_credentials: 'Email ou mot de passe incorrect.',

    // ── Inscription ────────────────────────────────────────────────
    register: 'Inscription',
    register_success: 'Entreprise créée avec succès ! Un email de bienvenue vous a été envoyé.',
    register_welcome: 'Bienvenue sur PrimeGest !',
    register_company_section: 'Informations entreprise',
    register_admin_section: 'Compte Super Admin',
    register_company_name: "Nom de l'entreprise *",
    register_company_email: 'Email entreprise *',
    register_company_phone: 'Téléphone',
    register_company_address: 'Adresse',
    register_admin_name: 'Nom complet *',
    register_admin_email: 'Email *',
    register_admin_password: 'Mot de passe *',
    register_admin_confirm: 'Confirmer *',
    register_submit: "Créer l'entreprise",
    register_submitting: 'Création en cours...',
    register_already: 'Déjà inscrit ?',
    register_signin: 'Se connecter',

    // ── Page d'accueil ─────────────────────────────────────────────
    welcome_title: 'Bienvenue sur',
    welcome_subtitle: "Un outil numérique moderne et performant conçu pour simplifier la gestion de votre entreprise, améliorer votre productivité et centraliser vos activités.",
    welcome_secondary: "PrimeGest vous accompagne dans la transformation numérique de vos activités, en vous offrant des outils simples, intuitifs et fiables pour une gestion moderne.",
    welcome_register: 'Inscription',
    welcome_login: 'Connexion',
  },

  en: {
    // ── Dashboard ──────────────────────────────────────────────────
    dashboard: 'DASHBOARD',
    total_stock: 'Total stock',
    total_sales: 'Total sales',
    total_expenses: 'Total expenses',
    stock_alerts: 'Stock alerts',
    stock_alerts_low: 'Low stock alerts',
    recent_activity: 'Recent activity',
    best_sales: 'Best sellers',
    shortcuts: 'Shortcuts',
    stock_value: 'Stock value',
    sales_recorded: 'Sales recorded',
    cash_out: 'Cash out',
    threshold: 'Threshold',
    no_activity: 'No recent activity.',
    no_sales: 'No sales recorded.',
    no_alerts: 'No alerts.',

    // ── Navigation ─────────────────────────────────────────────────
    cashier: 'Cashier',
    stock_moves: 'Stock movements',
    products: 'Products',
    clients: 'Clients',
    suppliers: 'Suppliers',
    debts: 'Receivables & payables',
    journal: 'Journal',
    archives: 'Archives',
    users: 'Users',
    settings: 'Settings',
    employees: 'Employees',
    payroll: 'Payslips',
    report: 'Report',
    reports: 'Reports',
    human_resources: 'Human resources',

    // ── Graphiques ─────────────────────────────────────────────────
    sales_7d: 'Sales last 7 days',
    sales_purchases_monthly: 'Monthly sales & purchases',
    sales_label: 'Sales',
    purchases_label: 'Purchases',
    chart_daily: 'Daily chart',
    chart_monthly: 'Monthly chart',
    chart_type: 'Show:',

    // ── Titres pages ───────────────────────────────────────────────
    archives_title: 'Archives',
    stock_moves_title: 'Stock movements',
    cash_title: 'Cash',

    // ── Actions ────────────────────────────────────────────────────
    view_report: 'View report',
    view_journal: 'View journal',
    add_product: 'Add product',
    new_sale: 'New sale',
    save: 'Save',
    cancel: 'Cancel',
    edit: 'Edit',
    delete: 'Delete',
    confirm: 'Confirm',
    close: 'Close',
    search: 'Search',
    add: 'Add',
    validate: 'Validate',
    reject: 'Reject',
    back: 'Back',
    download: 'Download',
    print: 'Print',
    export: 'Export',

    // ── Formulaires ────────────────────────────────────────────────
    name: 'Name',
    email: 'Email',
    password: 'Password',
    password_confirm: 'Confirm password',
    admin_password: 'Administrator password',
    role: 'Role',
    phone: 'Phone',
    address: 'Address',
    date: 'Date',
    amount: 'Amount',
    quantity: 'Quantity',
    product: 'Product',
    description: 'Description',
    status: 'Status',
    type: 'Type',
    total: 'Total',
    note: 'Note',

    // ── Erreurs ────────────────────────────────────────────────────
    error_password_incorrect: 'Incorrect password.',
    error_access_denied: 'Access denied. You do not have the required permissions.',
    error_access_restricted: 'Access to this page is restricted.',
    error_not_found: 'Resource not found.',
    error_server: 'Server error. Please try again.',
    error_required: 'This field is required.',
    error_email_invalid: 'Invalid email address.',
    error_email_taken: 'This email is already in use.',
    error_phone_taken: 'This phone number is already in use.',
    error_insufficient_stock: 'Insufficient stock for this product.',
    error_invalid_amount: 'Invalid amount.',
    error_same_branch: 'The destination branch must be different.',
    error_transfer_processed: 'This transfer has already been processed.',

    // ── Succès ─────────────────────────────────────────────────────
    success_saved: 'Successfully saved.',
    success_deleted: 'Successfully deleted.',
    success_updated: 'Successfully updated.',
    success_transfer_requested: 'Transfer request registered.',
    success_transfer_validated: 'Transfer validated successfully.',
    success_transfer_rejected: 'Transfer rejected.',

    // ── Statuts ────────────────────────────────────────────────────
    status_pending: 'Pending',
    status_validated: 'Validated',
    status_rejected: 'Rejected',
    status_paid: 'Paid',
    status_unpaid: 'Unpaid',
    status_active: 'Active',
    status_inactive: 'Inactive',

    // ── Caisse ─────────────────────────────────────────────────────
    cash_in: 'Cash in',
    cash_balance: 'Balance',
    initial_balance: 'Initial balance',
    operation_type: 'Operation type',

    // ── Mouvement stock ────────────────────────────────────────────
    entry: 'Entry',
    exit: 'Exit',
    sale: 'Sale',
    purchase: 'Purchase',
    payment_cash: 'Cash',
    payment_credit: 'Credit',
    payment_reduction: 'Discount',
    available_stock: 'Available stock',
    unit_price: 'Unit price',
    purchase_price: 'Purchase price',
    sale_price: 'Sale price',
    stock_threshold: 'Alert threshold',

    // ── Transferts ─────────────────────────────────────────────────
    transfer: 'Transfer',
    transfer_cash: 'Cash transfer',
    transfer_stock: 'Stock transfer',
    from_branch: 'From branch',
    to_branch: 'To branch',
    branch: 'Branch',
    branches: 'Branches',
    central_dashboard: 'Central dashboard',

    // ── RH ─────────────────────────────────────────────────────────
    employee: 'Employee',
    position: 'Position',
    salary: 'Salary',
    hire_date: 'Hire date',
    payslip: 'Payslip',

    // ── Factures ───────────────────────────────────────────────────
    invoice: 'Invoice',
    invoice_number: 'Invoice number',
    invoice_date: 'Invoice date',
    client: 'Client',
    tax: 'VAT',
    subtotal: 'Subtotal (excl. tax)',
    tax_amount: 'Tax amount',
    total_ttc: 'Total (incl. tax)',

    // ── Connexion ──────────────────────────────────────────────────
    login: 'Login',
    logout: 'Logout',
    company_name: 'Company name',
    remember_me: 'Remember me',
    forgot_password: 'Forgot password?',
    error_company_not_found: 'This company does not exist.',
    error_invalid_credentials: 'Incorrect email or password.',

    // ── Inscription ────────────────────────────────────────────────
    register: 'Register',
    register_success: 'Company successfully created! A welcome email has been sent.',
    register_welcome: 'Welcome to PrimeGest!',
    register_company_section: 'Company information',
    register_admin_section: 'Super Admin account',
    register_company_name: 'Company name *',
    register_company_email: 'Company email *',
    register_company_phone: 'Phone',
    register_company_address: 'Address',
    register_admin_name: 'Full name *',
    register_admin_email: 'Email *',
    register_admin_password: 'Password *',
    register_admin_confirm: 'Confirm *',
    register_submit: 'Create company',
    register_submitting: 'Creating...',
    register_already: 'Already registered?',
    register_signin: 'Sign in',

    // ── Page d'accueil ─────────────────────────────────────────────
    welcome_title: 'Welcome to',
    welcome_subtitle: 'A modern and powerful digital tool designed to simplify your business management, improve productivity and centralize your activities.',
    welcome_secondary: 'PrimeGest supports your digital transformation by providing simple, intuitive and reliable tools for modern management.',
    welcome_register: 'Register',
    welcome_login: 'Login',
  }
};

export function getStoredLang() {
  if (typeof localStorage === 'undefined') return 'fr';
  const value = localStorage.getItem(LANG_KEY);
  return value === 'en' ? 'en' : 'fr';
}

export function setLang(lang) {
  const value = lang === 'en' ? 'en' : 'fr';
  if (typeof localStorage !== 'undefined') {
    localStorage.setItem(LANG_KEY, value);
  }
  if (typeof document !== 'undefined') {
    document.documentElement.lang = value;
  }
  if (typeof window !== 'undefined') {
    window.dispatchEvent(new CustomEvent('primegest:lang'));
  }
}

export function applyStoredLang() {
  const lang = getStoredLang();
  if (typeof document !== 'undefined') {
    document.documentElement.lang = lang;
  }
}

export function t(key) {
  const lang = getStoredLang();
  return messages[lang]?.[key] ?? messages['fr'][key] ?? key;
}