const LANG_KEY = 'primegest_lang';

const messages = {
  fr: {
    dashboard: 'TABLEAU DE BORD',
    total_stock: 'Total des stocks',
    total_sales: 'Total des ventes',
    total_expenses: 'Total des dépenses',
    stock_alerts: 'Alertes stock',
    stock_alerts_low: 'Alertes stock bas',
    recent_activity: 'Activité récente',
    best_sales: 'Meilleures ventes',
    shortcuts: 'Raccourcis',
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
    archives_title: 'Archives',
    stock_moves_title: 'Mouvements de stock',
    cash_title: 'Caisse',
    view_report: 'Voir rapport',
    view_journal: 'Voir journal',
    add_product: 'Ajouter produit',
    new_sale: 'Nouvelle vente',
    human_resources: 'Ressources humaines',
    reports: 'Rapports',
    no_activity: 'Aucune activité récente.',
    no_sales: 'Aucune vente enregistrée.',
    no_alerts: 'Aucune alerte.',
    stock_value: 'Valeur du stock',
    sales_recorded: 'Sorties enregistrées',
    cash_out: 'Sorties caisse',
    threshold: 'Seuil',
    sales_7d: 'Ventes 7 derniers jours',
    sales_purchases_monthly: 'Ventes & achats mensuels',
    sales_label: 'Ventes',
    purchases_label: 'Achats',
    chart_daily: 'Graphique journalier',
    chart_monthly: 'Graphique mensuel',
    chart_type: 'Afficher :',
  },
  en: {
    dashboard: 'DASHBOARD',
    total_stock: 'Total stock',
    total_sales: 'Total sales',
    total_expenses: 'Total expenses',
    stock_alerts: 'Stock alerts',
    stock_alerts_low: 'Low stock alerts',
    recent_activity: 'Recent activity',
    best_sales: 'Best sellers',
    shortcuts: 'Shortcuts',
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
    archives_title: 'Archives',
    stock_moves_title: 'Stock movements',
    cash_title: 'Cash',
    view_report: 'View report',
    view_journal: 'View journal',
    add_product: 'Add product',
    new_sale: 'New sale',
    human_resources: 'Human resources',
    reports: 'Reports',
    no_activity: 'No recent activity.',
    no_sales: 'No sales recorded.',
    no_alerts: 'No alerts.',
    stock_value: 'Stock value',
    sales_recorded: 'Sales recorded',
    cash_out: 'Cash out',
    threshold: 'Threshold',
    sales_7d: 'Sales last 7 days',
    sales_purchases_monthly: 'Monthly sales & purchases',
    sales_label: 'Sales',
    purchases_label: 'Purchases',
    chart_daily: 'Daily chart',
    chart_monthly: 'Monthly chart',
    chart_type: 'Show:',
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
  return messages[lang]?.[key] ?? messages.fr[key] ?? key;
}
