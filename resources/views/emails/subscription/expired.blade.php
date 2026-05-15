<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Abonnement expiré</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:40px 0;">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

        <!-- Header -->
        <tr>
          <td style="background:#FDF8F0;padding:36px 40px 28px;text-align:center;border-bottom:3px solid #D4AF37;">
            <div style="margin-bottom:12px;">
              <img src="{{ $appUrl }}/images/primegest.png"
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

        <!-- Badge expiration -->
        <tr>
          <td style="padding:32px 40px 0;text-align:center;">
            <span style="display:inline-block;background:#FEE2E2;color:#991B1B;border:1px solid #FECACA;padding:6px 20px;border-radius:999px;font-size:13px;font-weight:700;letter-spacing:0.5px;">
              ✕ Abonnement {{ ucfirst($planAncien) }} expiré
            </span>
          </td>
        </tr>

        <!-- Contenu -->
        <tr>
          <td style="padding:28px 40px;">
            <p style="color:#374151;font-size:15px;margin:0 0 16px;">Bonjour <strong>{{ $userName }}</strong>,</p>
            <p style="color:#374151;font-size:15px;margin:0 0 16px;">
              Votre abonnement <strong>{{ ucfirst($planAncien) }}</strong> pour <strong>{{ $entrepriseName }}</strong>
              a expiré. Votre compte a été automatiquement repassé en plan <strong>Free</strong>.
            </p>

            <!-- Bloc rassurant -->
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#ECFDF5;border-radius:12px;padding:20px;margin:20px 0;">
              <tr>
                <td style="padding:8px 0;border-bottom:1px solid #A7F3D0;">
                  <span style="color:#065F46;font-size:13px;font-weight:600;">✓ Vos données sont conservées</span>
                </td>
              </tr>
              <tr>
                <td style="padding:8px 0;border-bottom:1px solid #A7F3D0;">
                  <span style="color:#065F46;font-size:13px;font-weight:600;">✓ Votre historique reste accessible</span>
                </td>
              </tr>
              <tr>
                <td style="padding:8px 0;">
                  <span style="color:#065F46;font-size:13px;font-weight:600;">✓ La synchronisation offline continue</span>
                </td>
              </tr>
            </table>

            <p style="color:#6B7280;font-size:14px;margin:0 0 24px;">
              Pour retrouver toutes les fonctionnalités avancées (exports PDF, suivi des créances,
              succursales…), renouvelez votre abonnement à tout moment.
            </p>

            <!-- CTA -->
            <div style="text-align:center;margin:28px 0;">
              <a href="{{ $appUrl }}/abonnement"
                 style="display:inline-block;background:#D4AF37;color:#000000;font-weight:700;font-size:14px;padding:14px 36px;border-radius:999px;text-decoration:none;letter-spacing:0.3px;">
                Renouveler mon abonnement
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
