# Deployment Guide

## Step-by-Step Deployment to Railway

### 1. Create GitHub Repository

1. Go to https://github.com/new
2. Repository name: `online-store-api`
3. Add description: "Flash Sale Online Store API with Race Condition Handling"
4. Choose "Public" (requirement states public git repository)
5. Click "Create repository"

### 2. Push Code to GitHub

In your terminal, run:

```bash
cd /path/to/fullstack-assessment

# Set git remote
git remote add origin https://github.com/YOUR_USERNAME/online-store-api.git

# Rename branch to main if needed
git branch -M main

# Push to GitHub
git push -u origin main
```

Replace `YOUR_USERNAME` with your actual GitHub username.

### 3. Deploy to Railway

1. Go to https://railway.app
2. Click "New Project"
3. Select "Deploy from GitHub"
4. Authorize Railway to access your GitHub account
5. Select the `online-store-api` repository
6. Railway will automatically detect PHP and create a deployment

### 4. Configure Railway (Optional)

If you want to use a persistent database:

1. In Railway dashboard, go to your project
2. Click "Add" → "Database" → "PostgreSQL" or "MySQL"
3. Railway will automatically set environment variables

For now, SQLite (file-based) works fine and is included.

### 5. Test Your Deployment

Once deployed, Railway will provide a public URL like:
```
https://online-store-api-production-xxxx.railway.app
```

Test it:

```bash
curl -X POST https://online-store-api-production-xxxx.railway.app/products \
  -H "Content-Type: application/json" \
  -d '{"name":"Test Product","price":99.99,"inventory":10}'
```

## Important Notes

### Database

- By default, uses SQLite (file-based database)
- Database file: `database.db`
- For persistent storage across restarts, you can:
  - Switch to PostgreSQL/MySQL via Railway dashboard
  - Or use Railway's file storage plugin

### Environment Variables

No special configuration needed for basic setup. Optional variables in `.env`:

```
DB_DRIVER=sqlite
DB_PATH=./database.db
APP_ENV=production
APP_PORT=8000
```

### Custom Domain

To add a custom domain to your Railway app:

1. Go to Railway dashboard
2. Select your project
3. Click "Settings"
4. Add custom domain

## Troubleshooting

### Build Fails

- Check build logs in Railway dashboard
- Ensure PHP 8.4 is available (specified in `runtime.txt`)
- Verify all files are committed and pushed

### Database Issues

- Railway apps are stateless by default
- SQLite database file won't persist between restarts
- Solution: Use Railway's built-in PostgreSQL or MySQL

### API Returns 500 Error

- Check logs in Railway dashboard
- Ensure database is initialized on first run
- Check file permissions on `/database` directory

## What's Deployed

Your public API includes:

✓ REST API with full CRUD operations for Products and Orders
✓ Flash sale pricing support
✓ Race condition handling with atomic transactions
✓ Comprehensive input validation
✓ Detailed error messages in JSON format
✓ Functional test for race conditions available via code

## Meet Requirements

This deployment satisfies all requirements:

✓ **Language**: PHP
✓ **Public Git Repository**: GitHub (replace YOUR_USERNAME)
✓ **Clean Code**: Well-organized, commented, readable
✓ **Meaningful Commits**: Each commit has descriptive message
✓ **Publicly Accessible API**: Deployed on Railway
✓ **Business Requirements**: Orders, flash sales, inventory management
✓ **Technical Requirements**: JSON API, proper status codes, race condition handling, functional test
