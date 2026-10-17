/**
 * @file services/conseil-service.js
 * @description Je gere les conseils : je recupere, j'enregistre, je supprime et je cherche par mot-cle.
 * @author Jeremy Ferrier
 * @version 1.0
 */

// ===========================================================================
// FICHIER : services/conseil-service.js — Logique métier des conseils
// ===========================================================================
// J'ai isolé la logique des conseils dans ce service pour les mêmes raisons
// que pour les orchidées : séparation des responsabilités et préparation à la
// migration Supabase. Mes composants visuels n'ont jamais à manipuler
// directement le localStorage.

// J'importe ma couche d'abstraction BDD pour parler comme avec Supabase.
import { db } from '../core/db.js';

/**
 * Je recupere tous les conseils (SELECT * FROM conseils).
 * @returns {Array} Le tableau de tous les conseils.
 */
export function getAllConseils() {
  const res = db.from('conseils').select().execute();
  if (res.error) {
    console.error('Erreur conseils', res.error);
    return [];
  }
  return res.data;
}

/**
 * Je recupere un seul conseil par son id.
 * @param {string} id - L'identifiant du conseil (ex: "conseils-123").
 * @returns {Object|null} Le conseil trouve ou null si introuvable.
 */
export function getConseilById(id) {
  const res = db.from('conseils').select().eq('id', id).single();
  if (res.error) return null;
  return res.data;
}

/**
 * J'enregistre un conseil (je cree ou je mets a jour selon s'il existe deja).
 * @param {Object} conseil - L'objet conseil avec au moins id, name, content.
 * @returns {void}
 * @example
 * saveConseil({ id: "c1", name: "Arrosage", content: "..." });
 */
export function saveConseil(conseil) {
  const existing = db.from('conseils').select().eq('id', conseil.id).execute();
  if (existing.data && existing.data.length > 0) {
    db.from('conseils').update(conseil).eq('id', conseil.id).execute();
  } else {
    if (!conseil.id) conseil.id = 'conseils-' + Date.now();
    db.from('conseils').insert(conseil).execute();
  }
}

/**
 * Je supprime un conseil par son id.
 * @param {string} id - L'identifiant du conseil a supprimer.
 * @returns {void}
 */
export function deleteConseil(id) {
  db.from('conseils').delete().eq('id', id).execute();
}

/**
 * Je cherche des conseils avec un mot-cle dans nom, contenu ou categorie.
 * @param {string} query - Le mot tape par l'utilisateur.
 * @returns {Array} Le tableau des conseils qui correspondent.
 * @example
 * const resultats = searchConseils("arrosage");
 */
export function searchConseils(query) {
  const all = getAllConseils();
  if (!query) return all;
  const q = query.toLowerCase();
  return all.filter(c =>
    (c.name && c.name.toLowerCase().includes(q)) ||
    (c.content && c.content.toLowerCase().includes(q)) ||
    (c.category && c.category.toLowerCase().includes(q))
  );
}
