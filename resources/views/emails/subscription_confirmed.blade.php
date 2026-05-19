<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Confirmation d'abonnement PrimeGest</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:40px 0;">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

        <!-- Header -->
	<!-- Header -->
<tr>
  <td style="background:#FDF8F0;padding:36px 40px 28px;text-align:center;border-bottom:3px solid #D4AF37;">
    <!-- Logo image si disponible, sinon texte stylisé -->
    <div style="margin-bottom:12px;">
      <img src="https://primegest.app/images/primegest.png"
           width="72" height="72"
           style="border-radius:50%;border:3px solid #D4AF37;box-shadow:0 0 0 4px rgba(212,175,55,0.15);display:block;margin:0 auto;"
           alt="PrimeGest"
           onerror="this.style.display='none'"/>
    </div>
    <div style="font-size:30px;font-weight:900;letter-spacing:-0.5px;line-height:1;">
      <span style="color:#D4AF37;">Prime</span><span style="color:#111827;">Gest</span>
    </div>
    <div style="color:#9CA3AF;font-size:11px;margin-top:6px;letter-spacing:2px;text-transform:uppercase;">
      Gestion commerciale
    </div>
  </td>
</tr>

        <!-- Badge plan -->
        <tr>
          <td style="padding:32px 40px 0;text-align:center;">
            @if($plan === 'premium')
              <span style="display:inline-block;background:#EFF6FF;color:#1D4ED8;border:1px solid #BFDBFE;padding:6px 20px;border-radius:999px;font-size:13px;font-weight:700;letter-spacing:0.5px;">
                ★ Plan Premium activé
              </span>
            @elseif($plan === 'pro')
              <span style="display:inline-block;background:#F5F3FF;color:#6D28D9;border:1px solid #DDD6FE;padding:6px 20px;border-radius:999px;font-size:13px;font-weight:700;letter-spacing:0.5px;">
                ⚡ Plan Pro activé
              </span>
            @elseif($isTrial)
              <span style="display:inline-block;background:#ECFDF5;color:#065F46;border:1px solid #A7F3D0;padding:6px 20px;border-radius:999px;font-size:13px;font-weight:700;letter-spacing:0.5px;">
                ○ Essai gratuit activé
              </span>
            @endif
          </td>
        </tr>

        <!-- Contenu -->
        <tr>
          <td style="padding:28px 40px;">
            <p style="color:#374151;font-size:15px;margin:0 0 16px;">Bonjour <strong>{{ $userName }}</strong>,</p>
            @if($isTrial)
              <p style="color:#374151;font-size:15px;margin:0 0 16px;">
                Votre essai gratuit du plan <strong>{{ ucfirst($plan) }}</strong> pour <strong>{{ $entrepriseName }}</strong> a été activé avec succès.
              </p>
            @else
              <p style="color:#374151;font-size:15px;margin:0 0 16px;">
                Votre abonnement au plan <strong>{{ ucfirst($plan) }}</strong> pour <strong>{{ $entrepriseName }}</strong> a été confirmé avec succès.
              </p>
            @endif

            <!-- Récapitulatif -->
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:12px;padding:20px;margin:20px 0;">
              <tr>
                <td style="padding:8px 0;border-bottom:1px solid #e5e7eb;">
                  <span style="color:#6B7280;font-size:13px;">Plan</span>
                  <span style="float:right;color:#111827;font-size:13px;font-weight:600;">{{ ucfirst($plan) }}</span>
                </td>
              </tr>
              <tr>
                <td style="padding:8px 0;border-bottom:1px solid #e5e7eb;">
                  <span style="color:#6B7280;font-size:13px;">Entreprise</span>
                  <span style="float:right;color:#111827;font-size:13px;font-weight:600;">{{ $entrepriseName }}</span>
                </td>
              </tr>
              <tr>
                <td style="padding:8px 0;border-bottom:1px solid #e5e7eb;">
                  <span style="color:#6B7280;font-size:13px;">Montant</span>
                  <span style="float:right;color:#111827;font-size:13px;font-weight:600;">
                    {{ $isTrial ? 'Gratuit' : $amount . ' $/mois' }}
                  </span>
                </td>
              </tr>
              <tr>
                <td style="padding:8px 0;">
                  <span style="color:#6B7280;font-size:13px;">
                    {{ $isTrial ? 'Essai jusqu\'au' : 'Valide jusqu\'au' }}
                  </span>
                  <span style="float:right;color:#111827;font-size:13px;font-weight:600;">{{ $expiresAt }}</span>
                </td>
              </tr>
            </table>

            <p style="color:#6B7280;font-size:14px;margin:0 0 24px;">
              Vous pouvez accéder à votre application dès maintenant et profiter de toutes les fonctionnalités de votre plan.
            </p>

            <!-- CTA -->
            <div style="text-align:center;margin:28px 0;">
              <a href="{{ $appUrl }}/dashboard"
                 style="display:inline-block;background:#D4AF37;color:#000000;font-weight:700;font-size:14px;padding:14px 36px;border-radius:999px;text-decoration:none;letter-spacing:0.3px;">
                Accéder à mon tableau de bord
              </a>
            </div>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background:#f9fafb;padding:20px 40px;text-align:center;border-top:1px solid #e5e7eb;">
            <p style="color:#9CA3AF;font-size:12px;margin:0;">
              © {{ date('Y') }} PrimeGest · <a href="{{ $appUrl }}" style="color:#9CA3AF;">primegest.app</a>
            </p>
            <p style="color:#D1D5DB;font-size:11px;margin:6px 0 0;">
              Cet email a été envoyé automatiquement, merci de ne pas y répondre.
            </p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
