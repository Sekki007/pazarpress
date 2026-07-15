CREATE TABLE IF NOT EXISTS restaurants (
    id TEXT PRIMARY KEY,
    ownerId TEXT NOT NULL,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    city TEXT NOT NULL,
    address TEXT,
    phone TEXT,
    whatsapp TEXT,
    description TEXT,
    logoImage TEXT,
    coverImage TEXT,
    hoursJson TEXT,
    status TEXT NOT NULL DEFAULT 'PENDING',
    qrCode TEXT UNIQUE,
    reviewsEnabled INTEGER NOT NULL DEFAULT 1,
    viewCount INTEGER NOT NULL DEFAULT 0,
    avgRating REAL,
    reviewCount INTEGER NOT NULL DEFAULT 0,
    publishedAt TEXT,
    createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ownerId) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_restaurants_status ON restaurants(status);
CREATE INDEX IF NOT EXISTS idx_restaurants_city ON restaurants(city);
CREATE INDEX IF NOT EXISTS idx_restaurants_owner ON restaurants(ownerId);
CREATE INDEX IF NOT EXISTS idx_restaurants_slug ON restaurants(slug);

CREATE TABLE IF NOT EXISTS menu_categories (
    id TEXT PRIMARY KEY,
    restaurantId TEXT NOT NULL,
    name TEXT NOT NULL,
    sortOrder INTEGER NOT NULL DEFAULT 0,
    createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurantId) REFERENCES restaurants(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_menu_categories_restaurant ON menu_categories(restaurantId);

CREATE TABLE IF NOT EXISTS menu_items (
    id TEXT PRIMARY KEY,
    categoryId TEXT NOT NULL,
    restaurantId TEXT NOT NULL,
    name TEXT NOT NULL,
    description TEXT,
    price REAL,
    priceLabel TEXT,
    currency TEXT NOT NULL DEFAULT 'RSD',
    image TEXT,
    tagsJson TEXT,
    isAvailable INTEGER NOT NULL DEFAULT 1,
    sortOrder INTEGER NOT NULL DEFAULT 0,
    createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (categoryId) REFERENCES menu_categories(id) ON DELETE CASCADE,
    FOREIGN KEY (restaurantId) REFERENCES restaurants(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_menu_items_category ON menu_items(categoryId);
CREATE INDEX IF NOT EXISTS idx_menu_items_restaurant ON menu_items(restaurantId);

CREATE TABLE IF NOT EXISTS restaurant_reviews (
    id TEXT PRIMARY KEY,
    restaurantId TEXT NOT NULL,
    name TEXT NOT NULL,
    rating INTEGER NOT NULL,
    body TEXT,
    status TEXT NOT NULL DEFAULT 'PENDING',
    ipHash TEXT,
    ownerReply TEXT,
    createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurantId) REFERENCES restaurants(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_restaurant_reviews_restaurant ON restaurant_reviews(restaurantId, status);
