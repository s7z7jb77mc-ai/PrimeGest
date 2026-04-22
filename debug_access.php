<?php
// debug_access.php
// Script pour tester les blocages d'accès sur le VPS

header('Content-Type: text/plain; charset=utf-8');

// 1. Tester CheckPlanLimit.php (limite atteinte)
function testCheckPlanLimit() {
    echo "=== Test CheckPlanLimit.php ===\n";
    try {
        require_once 'CheckPlanLimit.php';
        $checkPlan = new CheckPlanLimit();
        $result = $checkPlan->check(); // Supposons que cette méthode existe
        if (isset($result['limit_reached'])) {
            echo "❌ BLOCAGE: Limite atteinte (CheckPlanLimit.php)\n";
            echo "Détails: " . json_encode($result) . "\n";
        } else {
            echo "✅ OK: Aucune limite atteinte\n";
        }
    } catch (Exception $e) {
        echo "❌ ERREUR: " . $e->getMessage() . "\n";
    }
}

// 2. Tester UserController.php (accès réservé au Super Admin)
function testUserControllerSuperAdmin() {
    echo "\n=== Test UserController.php (Super Admin) ===\n";
    try {
        require_once 'UserController.php';
        $userController = new UserController();
        // Simuler un utilisateur non-Super Admin
        $_SESSION['user_role'] = 'admin'; // Remplace par la logique réelle
        $access = $userController->checkSuperAdminAccess();
        if ($access === false) {
            echo "❌ BLOCAGE: Accès réservé au Super Admin (UserController.php)\n";
        } else {
            echo "✅ OK: Accès autorisé\n";
        }
    } catch (Exception $e) {
        echo "❌ ERREUR: " . $e->getMessage() . "\n";
    }
}

// 3. Tester UserController.php (mot de passe incorrect)
function testUserControllerPassword() {
    echo "\n=== Test UserController.php (Mot de passe) ===\n";
    try {
        require_once 'UserController.php';
        $userController = new UserController();
        // Remplace par le vrai mot de passe du Super Admin (en clair ou haché)
        $storedPasswordHash = '$2y$10$exemple...'; // Exemple de hash
        $inputPassword = 'ton_mot_de_passe_super_admin';
        if (password_verify($inputPassword, $storedPasswordHash)) {
            echo "✅ OK: Mot de passe correct\n";
        } else {
            echo "❌ BLOCAGE: Mot de passe incorrect (UserController.php)\n";
            echo "Vérifie: \n";
            echo "- Le hash stocké en base\n";
            echo "- La méthode de vérification (password_verify)\n";
            echo "- Les anciennes versions du fichier\n";
        }
    } catch (Exception $e) {
        echo "❌ ERREUR: " . $e->getMessage() . "\n";
    }
}

// Exécuter les tests
testCheckPlanLimit();
testUserControllerSuperAdmin();
testUserControllerPassword();

echo "\n=== Fin des tests ===\n";
?>
