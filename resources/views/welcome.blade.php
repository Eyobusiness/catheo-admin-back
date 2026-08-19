<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cathéo API v1 — Plateforme de Gestion Pastorale & Catéchétique</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-dark: #090d16;
            --bg-card: rgba(18, 25, 41, 0.75);
            --bg-card-hover: rgba(28, 38, 61, 0.85);
            --border-glow: rgba(99, 102, 241, 0.25);
            --border-subtle: rgba(255, 255, 255, 0.08);
            --primary: #6366f1;
            --primary-glow: #818cf8;
            --accent-gold: #fbbf24;
            --accent-green: #10b981;
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --text-sub: #6b7280;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            min-height: 100vh;
            overflow-x: hidden;
            background-image: 
                radial-gradient(circle at 15% 15%, rgba(99, 102, 241, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 85% 85%, rgba(251, 191, 36, 0.08) 0%, transparent 45%),
                radial-gradient(circle at 50% 50%, rgba(16, 185, 129, 0.05) 0%, transparent 50%);
            background-attachment: fixed;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Navbar */
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px 0;
            border-bottom: 1px solid var(--border-subtle);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .logo-icon {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 22px;
            color: white;
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.4);
        }

        .logo-text {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: linear-gradient(to right, #ffffff, #cbd5e1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo-badge {
            background: rgba(99, 102, 241, 0.15);
            border: 1px solid rgba(99, 102, 241, 0.3);
            color: var(--primary-glow);
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .nav-links {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.25s ease;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.35);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(99, 102, 241, 0.5);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: #e2e8f0;
            border: 1px solid var(--border-subtle);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        /* Hero */
        .hero {
            padding: 70px 0 50px;
            text-align: center;
            position: relative;
        }

        .hero-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(251, 191, 36, 0.1);
            border: 1px solid rgba(251, 191, 36, 0.25);
            color: var(--accent-gold);
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 24px;
        }

        .hero-title {
            font-size: 52px;
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -1.5px;
            margin-bottom: 20px;
            background: linear-gradient(to right, #ffffff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            font-size: 18px;
            color: var(--text-muted);
            max-width: 760px;
            margin: 0 auto 36px;
            font-weight: 400;
        }

        .hero-actions {
            display: flex;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        /* Stats bar */
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 50px 0;
            background: var(--bg-card);
            backdrop-filter: blur(16px);
            border: 1px solid var(--border-glow);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 800;
            color: white;
            font-family: 'JetBrains Mono', monospace;
        }

        .stat-label {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        /* JSON Showcase Window */
        .json-showcase {
            margin: 60px 0 80px;
            background: #0d1322;
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }

        .window-header {
            background: rgba(18, 25, 41, 0.95);
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border-subtle);
            flex-wrap: wrap;
            gap: 12px;
        }

        .window-dots {
            display: flex;
            gap: 8px;
        }

        .dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        .dot-red { background: #ef4444; }
        .dot-yellow { background: #f59e0b; }
        .dot-green { background: #10b981; }

        .tabs-nav {
            display: flex;
            gap: 8px;
            overflow-x: auto;
        }

        .tab-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-subtle);
            color: var(--text-muted);
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            font-family: 'JetBrains Mono', monospace;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .tab-btn.active {
            background: rgba(99, 102, 241, 0.2);
            border-color: rgba(99, 102, 241, 0.5);
            color: var(--primary-glow);
        }

        .tab-btn:hover:not(.active) {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .window-body {
            padding: 24px;
            position: relative;
        }

        .endpoint-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            padding: 10px 16px;
            background: rgba(0, 0, 0, 0.4);
            border-radius: 8px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
        }

        .method-badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 11px;
        }

        .method-get { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .method-post { background: rgba(99, 102, 241, 0.2); color: #818cf8; }
        .status-200 { color: #34d399; font-weight: 600; margin-left: auto; }

        pre {
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            line-height: 1.6;
            color: #d1d5db;
            overflow-x: auto;
            max-height: 380px;
        }

        /* Syntax colors */
        .j-key { color: #a5b4fc; }
        .j-str { color: #34d399; }
        .j-num { color: #fcd34d; }
        .j-bool { color: #c084fc; }

        /* Features Section */
        .section-title {
            text-align: center;
            margin-bottom: 48px;
        }

        .section-title h2 {
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 8px;
        }

        .section-title p {
            color: var(--text-muted);
            font-size: 15px;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
            margin-bottom: 80px;
        }

        .card {
            background: var(--bg-card);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border-subtle);
            border-radius: 20px;
            padding: 32px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .card:hover {
            background: var(--bg-card-hover);
            border-color: var(--border-glow);
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(99, 102, 241, 0.15);
        }

        .card-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 10px;
            color: white;
        }

        .card-text {
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* Security Badges Section */
        .security-box {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.08), rgba(99, 102, 241, 0.08));
            border: 1px solid rgba(16, 185, 129, 0.25);
            border-radius: 24px;
            padding: 40px;
            margin-bottom: 80px;
        }

        .security-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }

        .security-badge {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .security-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
        }

        .sec-item {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 14px;
            padding: 18px;
        }

        .sec-item h4 {
            font-size: 14px;
            font-weight: 700;
            color: #f3f4f6;
            margin-bottom: 4px;
        }

        .sec-item p {
            font-size: 12px;
            color: var(--text-sub);
        }



        /* Footer */
        footer {
            border-top: 1px solid var(--border-subtle);
            padding: 40px 0;
            text-align: center;
            color: var(--text-sub);
            font-size: 13px;
        }

        footer a {
            color: var(--primary-glow);
            text-decoration: none;
        }
    </style>
</head>
<body>

    <div class="container">
        <!-- Navbar -->
        <nav>
            <a href="/" class="logo">
                <div class="logo-icon">✝</div>
                <span class="logo-text">Cathéo</span>
                <span class="logo-badge">API v1.0</span>
            </a>
            <div class="nav-links">
                <a href="/docs" class="btn btn-primary">
                    <span>📖 Swagger / API Docs</span>
                </a>
                <a href="/api/v1/health" target="_blank" class="btn btn-secondary">
                    <span>⚡ Health Check</span>
                </a>
            </div>
        </nav>

        <!-- Hero -->
        <section class="hero">
            <div class="hero-tag">
                <span>✨ Système de Gestion Pastorale & Catéchétique Multi-Paroissial</span>
            </div>
            <h1 class="hero-title">L'Écosystème Numérique<br>pour l'Église de Demain</h1>
            <p class="hero-subtitle">
                Cathéo API v1 est un moteur backend RESTful robuste, sécurisé et hautement performant conçu pour orchestrer la gestion des catéchumènes, les présences, l'évaluation sacramentelle, les finances et la communication paroissiale.
            </p>
            <div class="hero-actions">
                <a href="/docs" class="btn btn-primary" style="padding: 14px 28px; font-size: 15px;">
                    <span>📘 Accéder à la Documentation API Swagger</span>
                </a>
                <a href="/swagger.json" target="_blank" class="btn btn-secondary" style="padding: 14px 28px; font-size: 15px;">
                    <span>📄 Spécification OpenAPI (JSON)</span>
                </a>
            </div>
        </section>

        <!-- Stats Bar -->
        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-number">44</div>
                <div class="stat-label">Tables Relationnelles</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">55+</div>
                <div class="stat-label">Endpoints RESTful</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">100%</div>
                <div class="stat-label">Couverture Tests (55/55)</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">UUID v4</div>
                <div class="stat-label">Identifiants Sécurisés</div>
            </div>
        </div>

        <!-- Interactive JSON Showcase Window -->
        <div class="json-showcase">
            <div class="window-header">
                <div class="window-dots">
                    <div class="dot dot-red"></div>
                    <div class="dot dot-yellow"></div>
                    <div class="dot dot-green"></div>
                </div>
                <div class="tabs-nav">
                    <button class="tab-btn active" onclick="switchTab('catechumenes')">GET /catechumenes</button>
                    <button class="tab-btn" onclick="switchTab('auth')">POST /auth/login</button>
                    <button class="tab-btn" onclick="switchTab('dashboard')">GET /dashboard/summary</button>
                    <button class="tab-btn" onclick="switchTab('bulletin')">GET /evaluations/bulletin</button>
                </div>
            </div>
            
            <div class="window-body">
                <!-- Tab 1: Catechumenes List -->
                <div id="tab-catechumenes" class="tab-content">
                    <div class="endpoint-bar">
                        <span class="method-badge method-get">GET</span>
                        <span>/api/v1/catechumenes?page=1&per_page=15</span>
                        <span class="status-200">HTTP 200 OK</span>
                    </div>
                    <pre><code>{
  <span class="j-key">"status"</span>: <span class="j-str">"success"</span>,
  <span class="j-key">"data"</span>: [
    {
      <span class="j-key">"id"</span>: <span class="j-str">"8f4c2a7e-91d3-4b8a-b521-6e8c9a7d1234"</span>, <span class="j-key">// UUID Public</span>
      <span class="j-key">"code_catechumene"</span>: <span class="j-str">"CAT-2024-0001"</span>,
      <span class="j-key">"nom"</span>: <span class="j-str">"KOUADIO"</span>,
      <span class="j-key">"prenoms"</span>: <span class="j-str">"Jean-Baptiste"</span>,
      <span class="j-key">"sexe"</span>: <span class="j-str">"M"</span>,
      <span class="j-key">"date_naissance"</span>: <span class="j-str">"2014-05-12"</span>,
      <span class="j-key">"telephone_tuteur"</span>: <span class="j-str">"+225 0701020304"</span>,
      <span class="j-key">"est_baptise"</span>: <span class="j-bool">true</span>,
      <span class="j-key">"statut"</span>: <span class="j-str">"actif"</span>,
      <span class="j-key">"niveau_actuel"</span>: {
        <span class="j-key">"id"</span>: <span class="j-str">"e3b0c442-98fc-4c14-9af0-85f8c19ac701"</span>,
        <span class="j-key">"nom"</span>: <span class="j-str">"1ère Communion (Année 2)"</span>
      }
    }
  ],
  <span class="j-key">"meta"</span>: {
    <span class="j-key">"current_page"</span>: <span class="j-num">1</span>,
    <span class="j-key">"last_page"</span>: <span class="j-num">2</span>,
    <span class="j-key">"per_page"</span>: <span class="j-num">15</span>,
    <span class="j-key">"total"</span>: <span class="j-num">20</span>
  }
}</code></pre>
                </div>

                <!-- Tab 2: Auth Login -->
                <div id="tab-auth" class="tab-content" style="display: none;">
                    <div class="endpoint-bar">
                        <span class="method-badge method-post">POST</span>
                        <span>/api/v1/auth/login</span>
                        <span class="status-200">HTTP 200 OK</span>
                    </div>
                    <pre><code>{
  <span class="j-key">"status"</span>: <span class="j-str">"success"</span>,
  <span class="j-key">"message"</span>: <span class="j-str">"Connexion réussie"</span>,
  <span class="j-key">"data"</span>: {
    <span class="j-key">"token"</span>: <span class="j-str">"1|9a8b7c6d5e4f3a2b1c0d9e8f7a6b5c4d"</span>,
    <span class="j-key">"token_type"</span>: <span class="j-str">"Bearer"</span>,
    <span class="j-key">"user"</span>: {
      <span class="j-key">"uuid"</span>: <span class="j-str">"9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5d4c"</span>,
      <span class="j-key">"nom"</span>: <span class="j-str">"KOUASSI"</span>,
      <span class="j-key">"prenoms"</span>: <span class="j-str">"Ferdinand"</span>,
      <span class="j-key">"user_type"</span>: <span class="j-str">"admin"</span>,
      <span class="j-key">"profil"</span>: {
        <span class="j-key">"nom"</span>: <span class="j-str">"Super Admin Paroissial"</span>,
        <span class="j-key">"permissions"</span>: [<span class="j-str">"*"</span>]
      }
    }
  }
}</code></pre>
                </div>

                <!-- Tab 3: Dashboard Summary -->
                <div id="tab-dashboard" class="tab-content" style="display: none;">
                    <div class="endpoint-bar">
                        <span class="method-badge method-get">GET</span>
                        <span>/api/v1/dashboard/summary</span>
                        <span class="status-200">HTTP 200 OK</span>
                    </div>
                    <pre><code>{
  <span class="j-key">"status"</span>: <span class="j-str">"success"</span>,
  <span class="j-key">"data"</span>: {
    <span class="j-key">"statistiques_globales"</span>: {
      <span class="j-key">"total_catechumenes"</span>: <span class="j-num">142</span>,
      <span class="j-key">"total_animateurs"</span>: <span class="j-num">18</span>,
      <span class="j-key">"total_classes"</span>: <span class="j-num">8</span>,
      <span class="j-key">"taux_presence_moyen"</span>: <span class="j-num">94.5</span>
    },
    <span class="j-key">"situation_financiere"</span>: {
      <span class="j-key">"solde_caisse_actuel"</span>: <span class="j-num">845000.00</span>,
      <span class="j-key">"recettes_du_mois"</span>: <span class="j-num">210000.00</span>,
      <span class="j-key">"taux_recouvrement_tarifs"</span>: <span class="j-num">88.2</span>
    }
  }
}</code></pre>
                </div>

                <!-- Tab 4: Bulletin Trimestriel -->
                <div id="tab-bulletin" class="tab-content" style="display: none;">
                    <div class="endpoint-bar">
                        <span class="method-badge method-get">GET</span>
                        <span>/api/v1/bulletins/catechumene/8f4c2a7e-91d3-4b8a-b521-6e8c9a7d1234?trimestre=1</span>
                        <span class="status-200">HTTP 200 OK</span>
                    </div>
                    <pre><code>{
  <span class="j-key">"status"</span>: <span class="j-str">"success"</span>,
  <span class="j-key">"data"</span>: {
    <span class="j-key">"catechumene"</span>: <span class="j-str">"KOUADIO Jean-Baptiste"</span>,
    <span class="j-key">"trimestre"</span>: <span class="j-num">1</span>,
    <span class="j-key">"moyenne_generale"</span>: <span class="j-num">16.75</span>,
    <span class="j-key">"rang"</span>: <span class="j-str">"2ème sur 24"</span>,
    <span class="j-key">"appreciation_globale"</span>: <span class="j-str">"Très bon trimestre. Élève assidu et participatif."</span>,
    <span class="j-key">"decision_provisoire"</span>: <span class="j-str">"Admis au passage supérieur"</span>
  }
}</code></pre>
                </div>
            </div>
        </div>

        <!-- Features Grid -->
        <div class="section-title">
            <h2>Modules Métier Complètement Intégrés</h2>
            <p>Une couverture fonctionnelle à 360° pour les besoins des paroisses, diocèses et équipes pastorales</p>
        </div>

        <div class="grid-3">
            <!-- Feature 1 -->
            <div class="card">
                <div class="card-icon" style="background: rgba(99, 102, 241, 0.15); color: #818cf8;">👥</div>
                <h3 class="card-title">Catéchumènes & Preinscriptions</h3>
                <p class="card-text">
                    Gestion des préinscriptions publiques, validation administrative, suivi sacramental (Baptême, 1ère Communion, Confirmation), mutations entre paroisses et parrains/marraines.
                </p>
            </div>

            <!-- Feature 2 -->
            <div class="card">
                <div class="card-icon" style="background: rgba(251, 191, 36, 0.15); color: #fbbf24;">✝️</div>
                <h3 class="card-title">Organisation Pastorale & Calendrier</h3>
                <p class="card-text">
                    Structure dynamique par Années catéchétiques, Sections, Niveaux et Classes. Affectation des Animateurs, gestion des Groupes, Mouvements et Communautés Écclésiales de Base (CEB).
                </p>
            </div>

            <!-- Feature 3 -->
            <div class="card">
                <div class="card-icon" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">📝</div>
                <h3 class="card-title">Séances, Présences & Évaluations</h3>
                <p class="card-text">
                    Planification des séances de cours, enregistrement par lot des présences/absences, grilles de notes, calcul automatisé des bulletins trimestriels et décisions de fin d'année.
                </p>
            </div>

            <!-- Feature 4 -->
            <div class="card">
                <div class="card-icon" style="background: rgba(236, 72, 153, 0.15); color: #f472b6;">💳</div>
                <h3 class="card-title">Finances & Caisse Paroissiale</h3>
                <p class="card-text">
                    Tarification sur-mesure (inscription, manuels, retraites), encaissements multi-modes (Espèces, Mobile Money, Virement), reçus infalsifiables, remboursements traçables et journal de caisse.
                </p>
            </div>

            <!-- Feature 5 -->
            <div class="card">
                <div class="card-icon" style="background: rgba(14, 165, 233, 0.15); color: #38bdf8;">🔑</div>
                <h3 class="card-title">Authentification Multi-Acteurs</h3>
                <p class="card-text">
                    Logins adaptés aux besoins réels : Administrateur via Email, Animateur via N° Téléphone, Parent via Matricule Enfant. Profils personnalisables et jetons Sanctum.
                </p>
            </div>

            <!-- Feature 6 -->
            <div class="card">
                <div class="card-icon" style="background: rgba(168, 85, 247, 0.15); color: #c084fc;">📊</div>
                <h3 class="card-title">Tableaux de Bord & Exports PDF/CSV</h3>
                <p class="card-text">
                    Synthèses analytiques, KPIs d'effectifs et financiers, fiches de présences imprimables, suivi sacramental exportable et bilans annuels automatisés.
                </p>
            </div>
        </div>

        <!-- Security Section -->
        <div class="security-box">
            <div class="security-header">
                <span class="security-badge">🛡️ Normes de Sécurité Appliquées</span>
                <h3 style="font-size: 20px; font-weight: 700;">Conception Sécurisée & OWASP Compliant</h3>
            </div>
            <div class="security-grid">
                <div class="sec-item">
                    <h4>Double ID Architecture</h4>
                    <p>UUID v4 public pour éviter les attaques par énumération (Anti-IDOR).</p>
                </div>
                <div class="sec-item">
                    <h4>Permissions Granulaires (RBAC)</h4>
                    <p>Contrôle dynamique CRUD (`CheckPermission`) sur chaque endpoint.</p>
                </div>
                <div class="sec-item">
                    <h4>En-têtes HTTP de Sécurité</h4>
                    <p>`nosniff`, `X-Frame-Options: DENY`, `X-XSS-Protection` et CSP.</p>
                </div>
                <div class="sec-item">
                    <h4>Audit & Traçabilité</h4>
                    <p>Journalisation complète des actions et champs `created_by`/`updated_by`.</p>
                </div>
            </div>
        </div>


        <!-- Footer -->
        <footer>
            <p><strong>Cathéo API v1.0</strong> — Développé avec Laravel & Architecture RESTful par l'équipe d'ingénierie.</p>
            <p style="margin-top: 6px;">Consulter la documentation interactive sur <a href="/docs">/docs</a></p>
        </footer>
    </div>

    <script>
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(el => {
                el.style.display = 'none';
            });
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            // Show target tab
            document.getElementById('tab-' + tabName).style.display = 'block';
            // Set active class on clicked button
            event.currentTarget.classList.add('active');
        }
    </script>
</body>
</html>
