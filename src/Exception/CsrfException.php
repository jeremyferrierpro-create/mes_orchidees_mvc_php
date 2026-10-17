<?php
/**
 * Exception levée lorsqu'un jeton CSRF est absent ou invalide sur un POST.
 *
 * Exception applicative de type "erreur client" (400) : elle est interceptée par
 * le front-controller AVANT le catch(\Throwable) générique, afin qu'un rejet CSRF
 * ne soit pas re-qualifié à tort en erreur serveur 500.
 *
 * @package App\Exception
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Exception;

/**
 * Requête bloquée : le jeton CSRF soumis ne correspond pas à celui de la session.
 */
class CsrfException extends \RuntimeException
{
}
