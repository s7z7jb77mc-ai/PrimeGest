<div class="min-h-screen flex items-center justify-center bg-gray-100 py-12 px-4">
  <div class="max-w-md w-full bg-white shadow-md rounded-lg p-8 space-y-6">

    <!-- Logo PrimeGest -->
    <div class="flex justify-center">
      <img src="{{ asset('build/assets/primegest.png') }}" alt="PrimeGest Logo" class="h-16 w-auto" />
    </div>

    <form action="{{ route('entreprise.register') }}" method="POST" class="space-y-4">
      @csrf

      <!-- Section Entreprise -->
      <h3 class="text-xl font-semibold text-gray-700">Entreprise</h3>
      <input type="text" name="entreprise_name" placeholder="Nom de l'entreprise" required
             class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
      <input type="email" name="entreprise_email" placeholder="Email de l'entreprise" required
             class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
      <input type="text" name="phone" placeholder="Téléphone" required
             class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
      <input type="text" name="adresse" placeholder="Adresse"
             class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">

      <!-- Section Admin -->
      <h3 class="text-xl font-semibold text-gray-700 mt-6">Admin</h3>
      <input type="text" name="admin_name" placeholder="Nom complet" required
             class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
      <input type="email" name="admin_email" placeholder="Email admin" required
             class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
      <input type="password" name="admin_password" placeholder="Mot de passe" required
             class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
      <input type="password" name="admin_password_confirmation" placeholder="Confirmer mot de passe" required
             class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">

      <button type="submit"
              class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition-colors font-semibold">
        Créer entreprise
      </button>
    </form>
  </div>
</div>
