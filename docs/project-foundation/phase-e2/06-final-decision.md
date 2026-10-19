# 06 — Décisions, questions ouvertes et recommandation finale

## 1. Décisions recommandées (à valider par le porteur)

| # | Décision | Recommandation | Fondement |
|---|----------|----------------|-----------|
| D1 | Moteur de base de données | **Conserver MySQL/MariaDB** (mono-moteur, Option A) | docs 01-02-04 (VERIFIED) |
| D2 | Abandonner la piste PostgreSQL pour ce rendu | **Oui** | aucun bénéfice jury, coût élevé (doc 04 B/C) |
| D3 | Sortie en ligne pour le jury | **Hébergeur MySQL gratuit** (GoogieHost ou équivalent), plan B local | docs 03-05 |
| D4 | Schémas `schema_neon.sql` / `schema_supabase.sql` | **Archiver** (dossier `archive/` ou mention « hérité, non maintenu »), **ne pas** les faire passer pour une compatibilité active | doc 02 §3 (VERIFIED obsolètes) |
| D5 | Fichier `DEFAULT (UUID())` | Si l'hébergeur est MySQL < 8.0.13, **adapter le DDL** (et prévoir un import sans ce défaut) ; sinon inchangé | docs 02-03 (contraint par la version MySQL du compte) |
| D6 | Alignement `DB_PASS` / `DB_PASSWORD` | **Harmoniser** le nom de clé (le `.env` met `DB_PASSWORD`, `db.php` lit `DB_PASS`) ; **hors périmètre de cet audit** (aucun code modifié ici) | doc 01 §3 (VERIFIED écart) |
| D7 | Seed de démonstration public | **Créer** un seed sans adresse personnelle avant toute publication | doc 05 §2 |

## 2. Questions ouvertes (réponse = porteur / accès extérieur)

1. **Quelle version MySQL exacte** l'offre gratuite retenue fournit-elle ? 
   (détermine D5) → **BLOCKED** : nécessite le compte.
2. **GoogieHost est-il le choix retenu**, ou un autre mutualisé MySQL, ou Render
   + MySQL externe (déjà documenté dans `.env.production.example`) ?
3. Le jury exige-t-il une **URL publique**, ou une démo **locale** suffit-elle ?
   (change le niveau d'engagement sur l'hébergement gratuit)
4. Veut-on **garder** une trace PostgreSQL (archivée) comme preuve de compétence,
   ou la retirer du dépôt pour éviter toute ambiguïté ?
5. Le nom de variable de mot de passe (`DB_PASS` vs `DB_PASSWORD`) est-il corrigé
   **maintenant** (mini-fix hors audit) ou plus tard ?

## 3. Recommandation finale (reformulée pour un débutant)

> **Garde MySQL/MariaDB partout.** C'est déjà ce que le site utilise réellement
> (vérifié en se connectant à la base). Ne tente pas de passer à PostgreSQL pour
> faire le déploiement : le code contient des fonctions propres à MySQL
> (`MATCH…AGAINST` pour la recherche, `ON DUPLICATE KEY`) qui ne marchent pas sur
> PostgreSQL sans réécriture sérieuse. Pour montrer le site au jury à 0 €,
> prends un **hébergement gratuit en MySQL**, importe le même schéma et les mêmes
> seeds que chez toi, et garde **ton XAMPP local prêt** en cas de pépin Internet.
> Les fichiers `schema_neon.sql` / `schema_supabase.sql` sont des restes d'une
> ancienne version : ils ne prouvent **pas** que le site est compatible
> PostgreSQL aujourd'hui.

## 4. Récapitulatif des 7 livrables

- `INDEX.md` — synthèse et navigation.
- `01-real-database-configuration.md` — connexions réelles + environnements.
- `02-sql-engine-compatibility.md` — dépendances MySQL / état PostgreSQL.
- `03-hosting-googiehost-assessment.md` — faisabilité hébergement gratuit.
- `04-architecture-options.md` — comparaison A/B/C + reco.
- `05-jury-demo-plan.md` — plan de démo + secours.
- `06-final-decision.md` — le présent document.

## 5. Périmètre respecté

Aucun code applicatif modifié, aucun schéma/migration exécuté, aucune écriture en
base (diagnostic en **lecture seule** uniquement), aucun processus arrêté, aucun
déploiement/compte/push, aucun secret affiché (valeurs masquées), aucune
détection automatique de moteur implémentée. **Seuls nouveaux fichiers** : les
7 documents de ce dossier.
