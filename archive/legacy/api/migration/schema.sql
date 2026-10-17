-- ==============================================================================
-- BASE DE DONNEES : mes_orchidees
-- SGBD : PostgreSQL (Neon DB)
-- ==============================================================================

-- 1. Table des Utilisateurs
CREATE TABLE IF NOT EXISTS public.users (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Dictionnaire global des Orchidées (Encyclopédie)
CREATE TABLE IF NOT EXISTS public.orchids (
    id VARCHAR(100) PRIMARY KEY, -- ex: "acacalis_cyanea"
    name VARCHAR(100) NOT NULL,
    vernacular VARCHAR(100),
    tax_order VARCHAR(100),
    family VARCHAR(100),
    subfamily VARCHAR(100),
    tribu VARCHAR(100),
    subtribu VARCHAR(100),
    genre VARCHAR(100),
    species VARCHAR(100),
    behavior VARCHAR(50),
    discovered VARCHAR(255),
    origin VARCHAR(255),
    img VARCHAR(255),
    short_desc TEXT,
    long_desc TEXT
);

-- 3. Fiches Conseils
CREATE TABLE IF NOT EXISTS public.conseils (
    id VARCHAR(100) PRIMARY KEY, -- ex: "fiche-acacalis_cyanea"
    orchid_id VARCHAR(100) REFERENCES public.orchids(id) ON DELETE CASCADE,
    content TEXT,
    temperature VARCHAR(100),
    arrosage VARCHAR(100),
    hygrometrie VARCHAR(100),
    engrais VARCHAR(100)
);

-- 4. Collection Personnelle des Utilisateurs
CREATE TABLE IF NOT EXISTS public.user_collections (
    collection_id VARCHAR(100) PRIMARY KEY, -- identifiant unique "col-12345"
    user_id INTEGER NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
    orchid_id VARCHAR(100) REFERENCES public.orchids(id) ON DELETE SET NULL, -- NULL si plante personnalisée non reconnue
    custom_name VARCHAR(100), -- si la plante n'est pas dans l'encyclopédie
    img VARCHAR(255),
    behavior VARCHAR(50),
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    location VARCHAR(100),
    notes TEXT,
    temp VARCHAR(50),
    hygro VARCHAR(50),
    light VARCHAR(50),
    ventilation VARCHAR(50)
);

-- 5. Historique des Soins (Care History)
CREATE TABLE IF NOT EXISTS public.care_history (
    id VARCHAR(100) PRIMARY KEY, -- ex: "care-12345"
    collection_id VARCHAR(100) NOT NULL REFERENCES public.user_collections(collection_id) ON DELETE CASCADE,
    care_date DATE NOT NULL,
    types JSONB, -- Types de soins: arrosage, nutrition, rempotage, traitement
    engrais VARCHAR(100),
    substrat VARCHAR(100),
    ravageurs VARCHAR(100),
    cycles JSONB,
    reminder_date DATE
);

-- 6. Notifications (Alertes et Rappels)
CREATE TABLE IF NOT EXISTS public.notifications (
    id BIGINT PRIMARY KEY,
    user_id INTEGER REFERENCES public.users(id) ON DELETE CASCADE, -- NULL = notification globale admin
    date DATE NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE
);
