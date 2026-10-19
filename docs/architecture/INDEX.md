# docs/architecture — Index

| Fichier | Objet |
|---|---|
| [00-arbitrage-denormalisation.md](00-arbitrage-denormalisation.md) | Note d'arbitrage : pourquoi le projet adopte une **3NF sélective** plutôt qu'une 3NF stricte ou une dénormalisation pure. |
| [01-mcd-mld-aligne.md](01-mcd-mld-aligne.md) | MCD / MLD textuel **aligné sur la base réelle** (8 tables) après migration `20261018_lookup_types_soin_categories_conseil.sql`. |

## Source de vérité du schéma

- DDL complet et canonique : [`data/schema_mysql.sql`](../../data/schema_mysql.sql)
- Migration en vigueur (3NF sélective) : [`data/migrations/20261018_lookup_types_soin_categories_conseil.sql`](../../data/migrations/20261018_lookup_types_soin_categories_conseil.sql)
- Seeds de référence : [`data/seeds/`](../../data/seeds/)

## Vérification

- Suite HTTP (serveur réel + base isolée) : `php tests/http/run.php` — **234 PASS / 0 FAIL**
- Les 3 vérifications D10b contrôlent explicitement l'alimentation de `types_soin` et `categories_conseil`.
