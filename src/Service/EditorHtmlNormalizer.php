<?php

namespace App\Service;

/**
 * Normalise le HTML produit par l'éditeur riche avant affichage côté front.
 *
 * Rôle :
 * - corriger les structures HTML imparfaites générées par l'éditeur WYSIWYG ;
 * - transformer certains éléments non souhaités (ex: <div>) en paragraphes ;
 * - supprimer les paragraphes vides ;
 * - produire un HTML plus propre et plus cohérent pour le rendu Twig.
 *
 * Objectif :
 * obtenir un contenu éditorial stable, lisible et sémantiquement plus propre
 * pour les blocs SEO affichés sur le site.
 *
 * Important :
 * cette classe ne gère pas la sécurité HTML.
 * La sécurité est assurée en aval, dans EditorHtmlExtension, qui applique
 * le sanitizer `app.editor_content_sanitizer` sur la sortie de ce service.
 */
final class EditorHtmlNormalizer
{
    /**
     * Normalise le HTML issu de l'éditeur riche.
     *
     * @param string|null $html Contenu HTML brut enregistré en base.
     *
     * @return string HTML nettoyé et restructuré pour l'affichage front.
     */
    public function normalize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $content = $html;

        // Normalise les espaces insécables fréquemment générés par l'éditeur.
        $content = str_replace(["\xc2\xa0", '&nbsp;'], ' ', $content);

        // Uniformise les balises de saut de ligne.
        $content = preg_replace('~<br\s*/?>~i', '<br>', $content);

        // Convertit les <div> de l'éditeur en <p> pour un HTML plus sémantique.
        $content = preg_replace('~<div\b[^>]*>~i', '<p>', $content);
        $content = preg_replace('~</div>~i', '</p>', $content);

        // Deux sauts de ligne ou plus sont interprétés comme un changement de paragraphe.
        $content = preg_replace('~(?:<br>\s*){2,}~i', '</p><p>', $content);

        // Si le contenu ne commence pas déjà par un bloc structurant,
        // on encapsule l'ensemble dans un paragraphe.
        if (!preg_match('~^\s*<(p|ul|ol|h2|h3|h4|section|div|article|figure|blockquote)\b~i', $content)) {
            $content = '<p>' . $content . '</p>';
        }

        // Corrige certains cas de paragraphes dupliqués ou imbriqués.
        $content = preg_replace('~<p>\s*<p>~i', '<p>', $content);
        $content = preg_replace('~</p>\s*</p>~i', '</p>', $content);

        // Supprime les paragraphes vides ou composés uniquement d'espaces / <br>.
        $content = preg_replace('~<p>(?:\s|<br>)*</p>~i', '', $content);

        // Réduit les espaces multiples internes.
        $content = preg_replace('~[ \t]+~', ' ', $content);

        return trim($content);
    }
}
