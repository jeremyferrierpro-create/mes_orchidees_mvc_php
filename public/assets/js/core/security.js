/**
 * @file core/security.js
 * @description Je protege contre les attaques XSS en transformant les caracteres dangereux en texte inoffensif.
 * @author Jeremy Ferrier
 * @version 1.0
 */

// ===========================================================================
// FICHIER : core/security.js — Protection contre la faille XSS
// ===========================================================================
// J'ai créé cette petite librairie de sécurité pour me prémunir contre la faille
// XSS (Cross-Site Scripting). Pourquoi c'est crucial ? Parce que si j'injecte
// du contenu utilisateur (ex: nom d'orchidée, commentaire) avec innerHTML sans
// l'échapper, un attaquant pourrait y glisser <script>alert('hack')</script> et
// exécuter du code malveillant dans le navigateur de mes visiteurs.

/**
 * J'echappe les caracteres HTML dangereux pour eviter le XSS.
 * @param {string} text - Le texte brut a securiser (ex: nom d'orchidee tape par l'utilisateur).
 * @returns {string} Le texte securise avec &lt; &gt; &amp; etc.
 * @example
 * const safe = escapeHtml("<script>alert('hack')</script>"); // "&lt;script&gt;..."
 */
export function escapeHtml(text) {
    if (typeof text !== 'string') return text;
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    // J'utilise une RegExp globale pour remplacer d'un seul coup toutes les
    // occurrences. C'est plus performant que 5 replace() successifs.
    return text.replace(/[&<>"']/g, function (m) { return map[m]; });
}
