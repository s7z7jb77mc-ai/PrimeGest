<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Réinitialisation PrimeGest</title>
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
              Bonjour <strong>{{ $prenom }}</strong>,
            </p>

            {{-- Corps --}}
            <p style="color:#374151;font-size:15px;margin:0 0 8px;line-height:1.7;">
              Vous recevez cet email car une demande de réinitialisation de mot de passe
              a été effectuée pour le compte <strong>super administrateur</strong> associé
              à cette adresse email sur <strong>PrimeGest</strong>.
            </p>

            {{-- ── BOUTON CTA ── --}}
            <div style="text-align:center;margin:32px 0;">
              <a href="{{ $url }}"
                 style="display:inline-block;background:#D4AF37;color:#000000;font-weight:700;font-size:14px;padding:14px 36px;border-radius:999px;text-decoration:none;letter-spacing:0.3px;">
                Réinitialiser mon mot de passe
              </a>
            </div>

            {{-- ── BOÎTE EXPIRATION ── --}}
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#FFFBEB;border:1px solid #FCD34D;border-radius:12px;margin:0 0 24px;">
              <tr>
                <td style="padding:16px 20px;">
                  <p style="color:#92400E;font-size:13px;margin:0;line-height:1.6;">
                    ⏱&nbsp; Ce lien est valable <strong>30 minutes</strong> à compter de la réception
                    de cet email. Passé ce délai, vous devrez effectuer une nouvelle demande.
                  </p>
                </td>
              </tr>
            </table>

            {{-- Divider --}}
            <table width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;">
              <tr>
                <td style="border-top:1px solid #e5e7eb;font-size:0;line-height:0;">&nbsp;</td>
              </tr>
            </table>

            {{-- Notes de sécurité --}}
            <p style="color:#9CA3AF;font-size:12px;margin:0 0 8px;line-height:1.6;">
              Si vous n'avez pas demandé cette réinitialisation, ignorez simplement cet email.
              Votre mot de passe restera inchangé et aucune action n'est requise de votre part.
            </p>
            <p style="color:#9CA3AF;font-size:12px;margin:0;line-height:1.6;">
              Pour votre sécurité, ce lien ne peut être utilisé qu'<strong style="color:#6B7280;">une seule fois</strong>
              et sera automatiquement invalidé après usage.
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
