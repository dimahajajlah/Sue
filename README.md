# Sue


> A personal space to share your interests without the pressure of social media.

Sue is a hand-drawn, notebook-style personal website where users can create their own profile and share the things they like — movies, shows, books, music, skills, quotes, places, hot takes, and more.

Unlike traditional social media, Sue is focused on **sharing who you are without having to constantly socialize**.

## ✨ Features

- 👤 Multi-user profiles
- 🔐 Account registration and login
- 📓 Notebook-style personal pages
- 🎬 Movies
- 📺 TV shows
- 📚 Books
- 🎵 Music
- 💭 Hot takes
- 🧠 Skills & party tricks
- 💬 Quotes
- 📍 Places
- 📊 Personal stats
- ❓ Ask Me Anything
- ✍️ Guestbook
- ➕ Create custom pages
- 📋 Suggested page types
- 📥 Import movies, shows, books, and other lists from CSV/XLSX files
- 🔗 Pretty profile URLs
- 🛡️ Password hashing, sessions, and CSRF protection

## 🛠️ Tech Stack

### Main Sue application

- PHP
- MySQL / MariaDB
- SQLite for local development
- HTML
- CSS
- JavaScript
- Apache `.htaccess`

### CSV Converter

- HTML
- PHP
- JavaScript
- SheetJS

The browser version of the converter processes spreadsheets entirely on the client side.

## 📁 Project Structure

```text
Sue-Shorts-Hub/
│
├── sue/
│   ├── index.php
│   ├── signup.php
│   ├── login.php
│   ├── logout.php
│   ├── dashboard.php
│   ├── edit-section.php
│   ├── import.php
│   ├── u.php
│   ├── schema.sql
│   │
│   ├── assets/
│   │   ├── logo.png
│   │   └── style.css
│   │
│   └── includes/
│       ├── auth.php
│       ├── config.php
│       ├── db.php
│       ├── footer.php
│       ├── header.php
│       └── helpers.php
│
├── sue-converter/
│   ├── index.html
│   ├── index.php
│   └── README.md
│
├── artifacts/
├── lib/
├── scripts/
│
├── package.json
├── pnpm-workspace.yaml
├── tsconfig.json
└── README.md
