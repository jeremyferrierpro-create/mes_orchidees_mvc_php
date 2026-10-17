/**
 * @file services/collection-service.js
 * @description Je gere la collection personnelle via l'API PHP.
 * @author Jeremy Ferrier
 * @version 2.0 (Migration PHP)
 */

import { getCurrentUser } from './auth-service.js';

export async function getCollection() {
    const user = getCurrentUser();
    if (!user) return [];
    
    const res = await fetch('api/collections/index.php');
    if (!res.ok) return [];
    
    const json = await res.json();
    return json.data || [];
}

export async function saveCollection(dataArray) {
    const user = getCurrentUser();
    if (!user) return;
    
    const res = await fetch('api/collections/upsert.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ data: dataArray })
    });
    
    if (!res.ok) {
        throw new Error('Erreur de sauvegarde de la collection');
    }
}
