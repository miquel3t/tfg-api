CREATE TABLE legoset (
    id TEXT PRIMARY KEY,      -- ID del set (ej: 75347-1)
    name TEXT NOT NULL,          -- Nombre del set
    year INTEGER NOT NULL,       -- Año de lanzamiento
    theme TEXT,                  -- Tema del set (Star Wars, City...)
    imageurl TEXT                -- URL de una imagen del set
);
CREATE TABLE minifig (
    id TEXT PRIMARY KEY,         -- ID de la minifigura 
    name TEXT NOT NULL,          -- Nombre de la minifigura
    imageurl TEXT                -- URL de una imagen de la minifigura
);
CREATE TABLE minifiglegoset (
    id INTEGER PRIMARY KEY,      -- ID interno autoincremental
    idlegoset TEXT NOT NULL,  -- Referencia al set
    idminifig TEXT NOT NULL,     -- Referencia a la minifigura
    quantity INTEGER NOT NULL DEFAULT 1,  -- Cantidad en el set

    -- Claves foraneas con borrado en cascada
    FOREIGN KEY (idlegoset) REFERENCES legoset(id) ON DELETE CASCADE,
    FOREIGN KEY (idminifig) REFERENCES minifig(id) ON DELETE CASCADE
);
CREATE INDEX idx_minifiglegoset_idlegoset ON minifiglegoset(idlegoset);
CREATE INDEX idx_minifiglegoset_idminifig ON minifiglegoset(idminifig);
