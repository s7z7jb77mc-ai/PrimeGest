<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bienvenue sur PrimeGest</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:40px 0;">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

        {{-- ═══ HEADER ═══ --}}
        <tr>
          <td style="background:#FDF8F0;padding:36px 40px 28px;text-align:center;border-bottom:3px solid #D4AF37;">
            {{-- Logo --}}
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

        {{-- ═══ BODY ═══ --}}
        <tr>
          <td style="padding:32px 40px 28px;">

            {{-- Greeting --}}
            <p style="color:#374151;font-size:15px;margin:0 0 16px;">
              Bienvenue, <strong>{{ $super_adminName }}</strong>.
            </p>

            {{-- Introduction --}}
            <p style="color:#374151;font-size:15px;margin:0 0 20px;line-height:1.7;">
              Votre entreprise <strong>{{ $entrepriseName }}</strong> est maintenant active sur
              <strong>PrimeGest</strong>. Votre espace de gestion est prêt. Il ne reste plus qu'à commencer.
            </p>

            {{-- ── BOÎTE INFORMATIONS DE CONNEXION ── --}}
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:12px;padding:20px;margin:0 0 20px;">
              <tr>
                <td style="padding:0 0 10px;">
                  <span style="color:#D4AF37;font-size:13px;font-weight:700;letter-spacing:0.3px;">
                    Vos informations de connexion
                  </span>
                </td>
              </tr>
              <tr>
                <td style="padding:8px 0;border-top:1px solid #e5e7eb;">
                  <span style="color:#6B7280;font-size:13px;">Entreprise</span>
                  <span style="float:right;color:#111827;font-size:13px;font-weight:600;">{{ $entrepriseName }}</span>
                </td>
              </tr>
              <tr>
                <td style="padding:8px 0;border-top:1px solid #e5e7eb;">
                  <span style="color:#6B7280;font-size:13px;">Email</span>
                  <span style="float:right;color:#111827;font-size:13px;font-weight:600;">{{ $super_adminEmail }}</span>
                </td>
              </tr>
              <tr>
                <td style="padding:8px 0;border-top:1px solid #e5e7eb;">
                  <span style="color:#6B7280;font-size:13px;">Rôle</span>
                  <span style="float:right;color:#111827;font-size:13px;font-weight:600;">Super Admin</span>
                </td>
              </tr>
            </table>

            {{-- Ce que vous pouvez faire --}}
            <p style="color:#6B7280;font-size:14px;margin:0 0 12px;line-height:1.7;">
              PrimeGest réunit tout ce dont vous avez besoin pour gérer votre activité :
            </p>
            <table cellpadding="0" cellspacing="0" style="margin:0 0 24px;padding-left:4px;">
              <tr>
                <td style="font-size:13px;color:#374151;padding:4px 0;line-height:1.6;">
                  &bull;&nbsp; Vos clients, vos équipes et vos opérations
                </td>
              </tr>
              <tr>
                <td style="font-size:13px;color:#374151;padding:4px 0;line-height:1.6;">
                  &bull;&nbsp; Vos ventes et vos flux, en temps réel
                </td>
              </tr>
              <tr>
                <td style="font-size:13px;color:#374151;padding:4px 0;line-height:1.6;">
                  &bull;&nbsp; Vos finances, claires et maîtrisées
                </td>
              </tr>
              <tr>
                <td style="font-size:13px;color:#374151;padding:4px 0;line-height:1.6;">
                  &bull;&nbsp; Vos succursales, coordonnées depuis un seul espace
                </td>
              </tr>
            </table>

            {{-- ── BOUTON CTA ── --}}
            <div style="text-align:center;margin:28px 0;">
              <a href="{{ $loginUrl }}"
                 style="display:inline-block;background:#D4AF37;color:#000000;font-weight:700;font-size:14px;padding:14px 36px;border-radius:999px;text-decoration:none;letter-spacing:0.3px;">
                Accéder à mon espace →
              </a>
            </div>

            {{-- ── BOÎTE CONSEIL ── --}}
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#FFFBEB;border:1px solid #FCD34D;border-radius:12px;margin:0 0 24px;">
              <tr>
                <td style="padding:16px 20px;">
                  <p style="color:#92400E;font-size:13px;margin:0;line-height:1.6;">
                    ★&nbsp; Commencez par <strong>configurer vos paramètres</strong> — devise, langue, logo —
                    puis ajoutez vos produits et votre équipe. Votre activité sera opérationnelle en quelques minutes.
                  </p>
                </td>
              </tr>
            </table>

            {{-- Note finale --}}
            <p style="color:#9CA3AF;font-size:12px;margin:0;line-height:1.6;">
              Si vous n'êtes pas à l'origine de cette inscription, vous pouvez ignorer cet email.
              Aucune action n'est requise de votre part.
            </p>

          </td>
        </tr>

        {{-- ═══ FOOTER ═══ --}}
        <tr>
          <td style="background:#f9fafb;padding:20px 40px;text-align:center;border-top:1px solid #e5e7eb;">
            <p style="color:#9CA3AF;font-size:12px;margin:0;">
              © {{ date('Y') }} PrimeGest · <a href="https://primegest.app" style="color:#9CA3AF;">primegest.app</a>
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
