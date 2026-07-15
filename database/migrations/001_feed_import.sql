CREATE TABLE IF NOT EXISTS feed_sources (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    feedUrl TEXT NOT NULL,
    type TEXT NOT NULL DEFAULT 'rss',
    categoryId TEXT NOT NULL,
    authorId TEXT NOT NULL,
    city TEXT NOT NULL DEFAULT 'NOVI_PAZAR',
    active INTEGER NOT NULL DEFAULT 1,
    autoPublish INTEGER NOT NULL DEFAULT 0,
    useAiRewrite INTEGER NOT NULL DEFAULT 1,
    maxPerRun INTEGER NOT NULL DEFAULT 3,
    contentSelector TEXT,
    creditSource INTEGER NOT NULL DEFAULT 1,
    lastFetchedAt TEXT,
    lastError TEXT,
    createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (categoryId) REFERENCES categories(id),
    FOREIGN KEY (authorId) REFERENCES authors(id)
);

CREATE TABLE IF NOT EXISTS feed_import_log (
    id TEXT PRIMARY KEY,
    sourceId TEXT NOT NULL,
    externalId TEXT NOT NULL,
    externalUrl TEXT NOT NULL,
    articleId TEXT,
    status TEXT NOT NULL,
    message TEXT,
    createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (sourceId, externalId),
    FOREIGN KEY (sourceId) REFERENCES feed_sources(id) ON DELETE CASCADE,
    FOREIGN KEY (articleId) REFERENCES articles(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_feed_import_log_created ON feed_import_log(createdAt DESC);
