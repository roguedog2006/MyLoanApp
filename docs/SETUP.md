# Development Environment Setup

## Prerequisites

- Node.js 18+ (or Python 3.10+)
- PostgreSQL 14+
- Redis 7+
- Git
- Docker (optional but recommended)

## Backend Setup

### Using Node.js + Express

```bash
cd backend
npm install
cp .env.example .env
# Edit .env with your config
npm run dev
```

### Using Python + FastAPI

```bash
cd backend
python -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate
pip install -r requirements.txt
cp .env.example .env
# Edit .env with your config
uvicorn main:app --reload
```

## Frontend Setup

```bash
cd frontend
npm install
npm run dev
# Open http://localhost:3000
```

## Database Setup

```bash
# Create PostgreSQL database
createdb myloanapp

# Run migrations (once setup)
npm run migrate:latest
```

## Environment Variables

Create a `.env` file in backend root:

```
# Database
DATABASE_URL=postgresql://user:password@localhost:5432/myloanapp

# Redis
REDIS_URL=redis://localhost:6379

# Stripe (get from dashboard.stripe.com)
STRIPE_SECRET_KEY=sk_test_...
STRIPE_PUBLISHABLE_KEY=pk_test_...

# KYC Provider (example: Stripe Identity)
KYC_PROVIDER=stripe

# JWT
JWT_SECRET=your-secret-key-change-this

# Environment
NODE_ENV=development
```

## Running Tests

```bash
npm run test          # Unit tests
npm run test:e2e      # Integration tests
npm run test:coverage # With coverage report
```

## Docker Compose (Recommended)

```bash
docker-compose up -d
# Starts: app, postgres, redis
```

## Quick Start Checklist

- [ ] Node/Python installed
- [ ] PostgreSQL running locally
- [ ] Redis running locally
- [ ] `.env` file configured
- [ ] Dependencies installed (`npm install`)
- [ ] Database created
- [ ] Server running on `http://localhost:8000` (backend)
- [ ] Frontend running on `http://localhost:3000`

## Next Steps

See `../README.md` for project overview.
