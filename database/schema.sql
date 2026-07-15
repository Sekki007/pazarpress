CREATE TABLE IF NOT EXISTS users (
    id TEXT PRIMARY KEY,
    email TEXT NOT NULL UNIQUE,
    passwordHash TEXT NOT NULL,
    name TEXT,
    role TEXT NOT NULL DEFAULT 'admin',
    createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS authors (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    avatar TEXT,
    bio TEXT,
    userId TEXT UNIQUE,
    createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (userId) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS categories (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tags (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS articles (
    id TEXT PRIMARY KEY,
    slug TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    lead TEXT NOT NULL,
    body TEXT NOT NULL,
    coverImage TEXT,
    coverCaption TEXT,
    categoryId TEXT NOT NULL,
    city TEXT NOT NULL,
    authorId TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'DRAFT',
    isBreaking INTEGER NOT NULL DEFAULT 0,
    isFeatured INTEGER NOT NULL DEFAULT 0,
    publishedAt TEXT,
    viewCount INTEGER NOT NULL DEFAULT 0,
    readingTimeMin INTEGER NOT NULL DEFAULT 3,
    createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (categoryId) REFERENCES categories(id),
    FOREIGN KEY (authorId) REFERENCES authors(id)
);

CREATE INDEX IF NOT EXISTS idx_articles_status_published ON articles(status, publishedAt);
CREATE INDEX IF NOT EXISTS idx_articles_city ON articles(city);
CREATE INDEX IF NOT EXISTS idx_articles_breaking ON articles(isBreaking);
CREATE INDEX IF NOT EXISTS idx_articles_featured ON articles(isFeatured);

CREATE TABLE IF NOT EXISTS article_tags (
    articleId TEXT NOT NULL,
    tagId TEXT NOT NULL,
    PRIMARY KEY (articleId, tagId),
    FOREIGN KEY (articleId) REFERENCES articles(id) ON DELETE CASCADE,
    FOREIGN KEY (tagId) REFERENCES tags(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS comments (
    id TEXT PRIMARY KEY,
    articleId TEXT NOT NULL,
    name TEXT NOT NULL,
    body TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'PENDING',
    createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (articleId) REFERENCES articles(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_comments_article_status ON comments(articleId, status);

CREATE TABLE IF NOT EXISTS polls (
    id TEXT PRIMARY KEY,
    question TEXT NOT NULL,
    active INTEGER NOT NULL DEFAULT 1,
    createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS poll_options (
    id TEXT PRIMARY KEY,
    pollId TEXT NOT NULL,
    text TEXT NOT NULL,
    FOREIGN KEY (pollId) REFERENCES polls(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS poll_votes (
    id TEXT PRIMARY KEY,
    pollOptionId TEXT NOT NULL,
    ipHash TEXT NOT NULL,
    createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (pollOptionId, ipHash),
    FOREIGN KEY (pollOptionId) REFERENCES poll_options(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS videos (
    id TEXT PRIMARY KEY,
    title TEXT NOT NULL,
    youtubeId TEXT,
    videoUrl TEXT,
    duration TEXT,
    thumbnail TEXT,
    viewCount INTEGER NOT NULL DEFAULT 0,
    publishedAt TEXT,
    createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id TEXT PRIMARY KEY,
    email TEXT NOT NULL UNIQUE,
    confirmed INTEGER NOT NULL DEFAULT 0,
    token TEXT NOT NULL UNIQUE,
    createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS reader_submissions (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    contact TEXT NOT NULL,
    message TEXT NOT NULL,
    attachments TEXT NOT NULL DEFAULT '[]',
    read INTEGER NOT NULL DEFAULT 0,
    createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS auto_vesti_settings (
    settingKey TEXT PRIMARY KEY,
    value TEXT NOT NULL,
    updatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
