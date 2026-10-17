/**
 * @file services/orchid-service.js
 * @description Je gere les orchidees via l'API PHP avec fallback sur JSON local.
 * @author Jeremy Ferrier
 * @version 2.1 (Fallback JSON local)
 */

let cachedOrchids = null;

export async function getAllOrchids() {
    // Utilise le cache si disponible
    if (cachedOrchids) return cachedOrchids;

    try {
        const res = await fetch('api/orchids/index.php');
        if (!res.ok) throw new Error('Erreur API');
        
        const data = await res.json();
        cachedOrchids = data;
        return data;
    } catch (error) {
        console.warn('API indisponible, fallback sur JSON local:', error);
        // Fallback sur fichier JSON local
        const res = await fetch('assets/js/data/orchids.json');
        if (!res.ok) throw new Error('Erreur de chargement du fichier local');
        
        const data = await res.json();
        cachedOrchids = data;
        return data;
    }
}

export async function getOrchidById(id) {
    const all = await getAllOrchids();
    return all.find(o => o.id === id) || null;
}

export async function searchOrchids(query) {
    if (!query || query.length < 2) return await getAllOrchids();
    
    const all = await getAllOrchids();
    const cleanQuery = query.toLowerCase();
    return all.filter(orchid => 
        orchid.name.toLowerCase().includes(cleanQuery) ||
        orchid.vernacular.toLowerCase().includes(cleanQuery) ||
        orchid.shortDesc.toLowerCase().includes(cleanQuery)
    );
}

export async function deleteOrchid(id) { console.warn('deleteOrchid not implemented'); }
