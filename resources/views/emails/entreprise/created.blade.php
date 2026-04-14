<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Bienvenue sur PrimeGest</title>
</head>
<body style="margin:0; padding:0; background-color:#F5F0E8; font-family:Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" role="presentation"
       style="background-color:#F5F0E8; padding:32px 0;">
<tr>
<td align="center">
<table width="570" cellpadding="0" cellspacing="0" role="presentation"
       style="background:#ffffff; border-radius:12px; border:1px solid #DDD5C8; overflow:hidden;">

  {{-- ═══ HEADER ═══ --}}
  <tr>
    <td style="background-color:#d8c2a3; padding:24px 40px; text-align:center;
               border-bottom:1px solid #C8B090;">

      {{-- Logo carré bleu --}}
      <div style="display:inline-block; width:56px; height:56px;
                  background:#1A56A0; border-radius:10px; text-align:center;
                  vertical-align:middle; line-height:56px;">
        <svg width="30" height="30" viewBox="0 0 30 30" fill="none"
             xmlns="http://www.w3.org/2000/svg"
             style="vertical-align:middle; margin-top:13px;">
          <rect x="3"  y="3"  width="10" height="10" rx="2" fill="white" opacity="0.95"/>
          <rect x="17" y="3"  width="10" height="10" rx="2" fill="white" opacity="0.6"/>
          <rect x="3"  y="17" width="10" height="10" rx="2" fill="white" opacity="0.6"/>
          <rect x="17" y="17" width="10" height="10" rx="2" fill="white" opacity="0.3"/>
        </svg>
      </div>

      <div style="font-size:20px; font-weight:700; color:#1A56A0;
                  font-family:Arial,sans-serif; margin-top:10px;">
        PrimeGest
      </div>
      <div style="font-size:11px; color:#7A6248; margin-top:4px;
                  font-family:Arial,sans-serif; letter-spacing:0.04em;">
        Gestion commerciale intelligente
      </div>
    </td>
  </tr>

  {{-- ═══ BODY ═══ --}}
  <tr>
    <td style="padding:36px 40px 28px; background:#ffffff;">

      {{-- Greeting --}}
      <p style="font-size:15px; font-weight:500; color:#1a1a1a;
                margin:0 0 16px; font-family:Arial,sans-serif;">
        Bienvenue, {{ $super_adminName }}.
      </p>

      {{-- Introduction --}}
      <p style="font-size:14px; color:#444444; line-height:1.7;
                margin:0 0 14px; font-family:Arial,sans-serif; text-align:justify;">
        Votre entreprise <strong style="color:#0B2D5E;">{{ $entrepriseName }}</strong>
        est maintenant active sur <strong style="color:#1A56A0;">PrimeGest</strong>.
        Votre espace de gestion est prêt. Il ne reste plus qu'à commencer.
      </p>

      {{-- ── BOÎTE INFORMATIONS DE CONNEXION ── --}}
      <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
             style="margin:16px 0 20px;">
      <tr>
        <td style="background:#F0F6FF; border:1px solid #B8D0F0; border-radius:8px;
                   padding:16px 20px;">
          <p style="font-size:13px; font-weight:700; color:#1A56A0;
                    margin:0 0 10px; font-family:Arial,sans-serif;">
            Vos informations de connexion
          </p>
          <table cellpadding="0" cellspacing="0" role="presentation" width="100%">
            <tr>
              <td style="font-size:13px; color:#555555; font-family:Arial,sans-serif;
                         padding:4px 0; width:110px;">
                Entreprise
              </td>
              <td style="font-size:13px; color:#0B2D5E; font-weight:600;
                         font-family:Arial,sans-serif; padding:4px 0;">
                {{ $entrepriseName }}
              </td>
            </tr>
            <tr>
              <td style="font-size:13px; color:#555555; font-family:Arial,sans-serif;
                         padding:4px 0;">
                Email
              </td>
              <td style="font-size:13px; color:#1A56A0; font-family:Arial,sans-serif;
                         padding:4px 0;">
                {{ $super_adminEmail }}
              </td>
            </tr>
            <tr>
              <td style="font-size:13px; color:#555555; font-family:Arial,sans-serif;
                         padding:4px 0;">
                Rôle
              </td>
              <td style="font-size:13px; color:#0B2D5E; font-weight:600;
                         font-family:Arial,sans-serif; padding:4px 0;">
                Super Admin
              </td>
            </tr>
          </table>
        </td>
      </tr>
      </table>

      {{-- Ce que vous pouvez faire --}}
      <p style="font-size:14px; color:#444444; line-height:1.7;
                margin:0 0 10px; font-family:Arial,sans-serif; text-align:justify;">
        PrimeGest réunit tout ce dont vous avez besoin pour gérer votre activité :
      </p>

      <table cellpadding="0" cellspacing="0" role="presentation" style="margin:0 0 20px; padding-left:8px;">
        <tr>
          <td style="font-size:13px; color:#444444; font-family:Arial,sans-serif;
                     padding:3px 0; line-height:1.6;">
            &bull;&nbsp; Vos clients, vos équipes et vos opérations
          </td>
        </tr>
        <tr>
          <td style="font-size:13px; color:#444444; font-family:Arial,sans-serif;
                     padding:3px 0; line-height:1.6;">
            &bull;&nbsp; Vos ventes et vos flux, en temps réel
          </td>
        </tr>
        <tr>
          <td style="font-size:13px; color:#444444; font-family:Arial,sans-serif;
                     padding:3px 0; line-height:1.6;">
            &bull;&nbsp; Vos finances, claires et maîtrisées
          </td>
        </tr>
        <tr>
          <td style="font-size:13px; color:#444444; font-family:Arial,sans-serif;
                     padding:3px 0; line-height:1.6;">
            &bull;&nbsp; Vos succursales, coordonnées depuis un seul espace
          </td>
        </tr>
      </table>

      {{-- ── BOUTON CTA ── --}}
      <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
      <tr>
        <td align="center" style="padding:20px 0 24px;">
          <a href="{{ $loginUrl }}"
             style="display:inline-block; background:#1A56A0; color:#ffffff;
                    font-size:14px; font-weight:500; font-family:Arial,sans-serif;
                    padding:14px 40px; border-radius:8px; text-decoration:none;
                    letter-spacing:0.01em;">
            Accéder à mon espace &rarr;
          </a>
        </td>
      </tr>
      </table>

      {{-- ── BOÎTE CONSEIL ── --}}
      <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
             style="margin:0 0 20px;">
      <tr>
        <td style="background:#FEF9F0; border:1px solid #E8C87A; border-radius:8px;
                   padding:12px 16px;">
          <table cellpadding="0" cellspacing="0" role="presentation">
          <tr>
            <td style="vertical-align:top; padding-right:10px; padding-top:2px;">
              {{-- Icône étoile --}}
              <svg width="18" height="18" viewBox="0 0 18 18" fill="none"
                   xmlns="http://www.w3.org/2000/svg">
                <polygon points="9,2 11,7 17,7 12,11 14,17 9,13 4,17 6,11 1,7 7,7"
                         fill="#C89020" opacity="0.85"/>
              </svg>
            </td>
            <td>
              <span style="font-size:13px; color:#7A5800;
                           font-family:Arial,sans-serif; line-height:1.55;">
                Commencez par <strong>configurer vos paramètres</strong> —
                devise, langue, logo — puis ajoutez vos produits et votre équipe.
                Votre activité sera opérationnelle en quelques minutes.
              </span>
            </td>
          </tr>
          </table>
        </td>
      </tr>
      </table>

      {{-- ── DIVIDER ── --}}
      <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
             style="margin:22px 0;">
      <tr>
        <td style="border-top:1px solid #EDE8E0; font-size:0; line-height:0;">&nbsp;</td>
      </tr>
      </table>

      {{-- Note finale --}}
      <p style="font-size:12px; color:#888888; line-height:1.6;
                margin:0; font-family:Arial,sans-serif; text-align:justify;">
        Si vous n'êtes pas à l'origine de cette inscription, vous pouvez ignorer
        cet email. Aucune action n'est requise de votre part.
      </p>

    </td>
  </tr>

  {{-- ═══ FOOTER ═══ --}}
  <tr>
    <td style="background:#d8c2a3; border-top:1px solid #C8B090;
               padding:20px 40px; text-align:center;">
      <p style="font-size:13px; font-weight:700; color:#1A56A0;
                margin:0 0 3px; font-family:Arial,sans-serif;">
        PrimeGest
      </p>
      <p style="font-size:12px; color:#6A5040; margin:0 0 8px;
                line-height:1.5; font-family:Arial,sans-serif;">
        Application de gestion commerciale de petite et Moyenne entreprise
      </p>
      <p style="font-size:11px; color:#7A6248; margin:0;
                font-family:Arial,sans-serif;">
        Cet email a été envoyé automatiquement par PrimeGest, merci de ne pas y répondre.
      </p>
    </td>
  </tr>

</table>
</td>
</tr>
</table>

</body>
</html>