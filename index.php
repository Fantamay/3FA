<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SecureHealth – Sécurité médicale avancée</title>
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700&display=swap" rel="stylesheet">
    <style>
        /* --- RESET & BASE --- */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body {
            font-family: 'Montserrat', Arial, sans-serif;
            background: linear-gradient(135deg, #181829 0%, #2d1e4f 100%);
            color: #f5f6fa;
            min-height: 100vh;
            scroll-behavior: smooth;
        }
        a { color: inherit; text-decoration: none; }
        ul { list-style: none; }
        img { max-width: 100%; height: auto; }
        /* --- NAVBAR --- */
        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.2rem 2.5rem;
            background: rgba(24,24,41,0.98);
            box-shadow: 0 2px 16px 0 rgba(0,0,0,0.12);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .navbar .logo {
            font-size: 2rem;
            font-weight: 700;
            color: #8f5fff;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
        }
        .navbar .logo i {
            margin-right: 0.5rem;
        }
        .navbar nav {
            display: flex;
            gap: 2rem;
        }
        .navbar nav a {
            font-weight: 500;
            transition: color 0.2s;
        }
        .navbar nav a:hover {
            color: #8f5fff;
        }
        .navbar .actions {
            display: flex;
            gap: 1rem;
        }
        .btn {
            padding: 0.7rem 1.6rem;
            border-radius: 30px;
            border: none;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.2s, color 0.2s, box-shadow 0.2s;
            outline: none;
        }
        .btn-primary {
            background: linear-gradient(90deg, #8f5fff 0%, #3e8eff 100%);
            color: #fff;
            box-shadow: 0 2px 8px 0 rgba(143,95,255,0.15);
        }
        .btn-primary:hover {
            background: linear-gradient(90deg, #3e8eff 0%, #8f5fff 100%);
        }
        .btn-outline {
            background: transparent;
            border: 2px solid #8f5fff;
            color: #8f5fff;
        }
        .btn-outline:hover {
            background: #8f5fff;
            color: #fff;
        }
        /* --- HERO --- */
        .hero {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            padding: 4rem 2.5rem 2rem 2.5rem;
            min-height: 80vh;
            position: relative;
            overflow: hidden;
        }
        .hero-content {
            flex: 1 1 400px;
            z-index: 2;
        }
        .hero-title {
            font-size: 2.7rem;
            font-weight: 700;
            margin-bottom: 1.2rem;
            color: #fff;
            line-height: 1.2;
            letter-spacing: 1px;
            position: relative;
        }
        .badge-military {
            display: inline-block;
            background: linear-gradient(90deg, #3e8eff 0%, #8f5fff 100%);
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 20px;
            padding: 0.3rem 1.1rem;
            margin-bottom: 1.1rem;
            box-shadow: 0 2px 8px 0 rgba(62,142,255,0.13);
            letter-spacing: 0.5px;
            animation: badgePop 1.2s cubic-bezier(.17,.67,.83,.67) 1;
        }
        @keyframes badgePop {
            0% { transform: scale(0.7); opacity: 0; }
            80% { transform: scale(1.1); opacity: 1; }
            100% { transform: scale(1); }
        }
        .hero-desc {
            font-size: 1.25rem;
            color: #d1d1e0;
            margin-bottom: 2rem;
            max-width: 500px;
        }
        .hero-cta {
            display: flex;
            gap: 1.2rem;
            margin-bottom: 2.5rem;
        }
        .hero-img {
            flex: 1 1 350px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 1;
        }
        .hero-img .scan-effect {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            pointer-events: none;
            z-index: 2;
        }
        .hero-img img {
            width: 350px;
            max-width: 90vw;
            filter: drop-shadow(0 8px 32px #8f5fff44);
            border-radius: 24px;
        }
        /* --- LOADER --- */
        #loader {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background: #181829;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.5s;
        }
        #loader .lds-ring {
            display: inline-block;
            position: relative;
            width: 80px;
            height: 80px;
        }
        #loader .lds-ring div {
            box-sizing: border-box;
            display: block;
            position: absolute;
            width: 64px;
            height: 64px;
            margin: 8px;
            border: 8px solid #8f5fff;
            border-radius: 50%;
            animation: lds-ring 1.2s cubic-bezier(.5,0,.5,1) infinite;
            border-color: #8f5fff transparent transparent transparent;
        }
        #loader .lds-ring div:nth-child(1) { animation-delay: -0.45s; }
        #loader .lds-ring div:nth-child(2) { animation-delay: -0.3s; }
        #loader .lds-ring div:nth-child(3) { animation-delay: -0.15s; }
        @keyframes lds-ring {
            0% { transform: rotate(0deg);}
            100% { transform: rotate(360deg);}
        }
        /* --- SECTIONS --- */
        section {
            padding: 4rem 2.5rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        .section-title {
            font-size: 2rem;
            font-weight: 700;
            color: #8f5fff;
            margin-bottom: 2rem;
            text-align: center;
        }
        /* --- FONCTIONNALITÉS --- */
        .features {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            justify-content: center;
        }
        .feature-card {
            background: #23234a;
            border-radius: 18px;
            padding: 2rem 1.5rem;
            flex: 1 1 220px;
            min-width: 220px;
            max-width: 270px;
            box-shadow: 0 2px 16px 0 rgba(143,95,255,0.07);
            display: flex;
            flex-direction: column;
            align-items: center;
            opacity: 0;
            transform: translateY(40px);
            transition: opacity 0.7s, transform 0.7s;
        }
        .feature-card.visible {
            opacity: 1;
            transform: translateY(0);
        }
        .feature-card i {
            font-size: 2.5rem;
            color: #3e8eff;
            margin-bottom: 1rem;
        }
        .feature-title {
            font-size: 1.15rem;
            font-weight: 600;
            margin-bottom: 0.7rem;
            color: #fff;
            text-align: center;
        }
        .feature-desc {
            color: #bdbde6;
            font-size: 1rem;
            text-align: center;
        }
        /* --- SÉCURITÉ --- */
        .security-section {
            background: linear-gradient(90deg, #23234a 0%, #2d1e4f 100%);
            border-radius: 18px;
            box-shadow: 0 2px 16px 0 rgba(62,142,255,0.07);
            margin-bottom: 2rem;
        }
        .security-content {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            align-items: center;
            justify-content: center;
        }
        .security-info {
            flex: 2 1 320px;
        }
        .security-info h3 {
            color: #3e8eff;
            font-size: 1.2rem;
            margin-bottom: 0.7rem;
        }
        .security-info ul {
            margin-left: 1.2rem;
            margin-bottom: 1rem;
        }
        .security-info li {
            margin-bottom: 0.5rem;
            color: #bdbde6;
        }
        .security-illustration {
            flex: 1 1 200px;
            text-align: center;
        }
        .security-illustration i {
            font-size: 4rem;
            color: #8f5fff;
        }
        /* --- DEMO/ATTAQUE --- */
        .demo-section {
            background: #23234a;
            border-radius: 18px;
            padding: 2.5rem 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 16px 0 rgba(62,142,255,0.07);
            text-align: center;
        }
        .demo-section strong {
            color: #3e8eff;
        }
        .demo-section .btn {
            margin-top: 1.2rem;
        }
        /* --- AVANTAGES --- */
        .advantages {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            justify-content: center;
        }
        .advantage-card {
            background: #23234a;
            border-radius: 18px;
            padding: 1.5rem 1.2rem;
            flex: 1 1 180px;
            min-width: 180px;
            max-width: 220px;
            box-shadow: 0 2px 16px 0 rgba(62,142,255,0.07);
            text-align: center;
            opacity: 0;
            transform: translateY(40px);
            transition: opacity 0.7s, transform 0.7s;
        }
        .advantage-card.visible {
            opacity: 1;
            transform: translateY(0);
        }
        .advantage-card i {
            font-size: 2rem;
            color: #8f5fff;
            margin-bottom: 0.7rem;
        }
        .advantage-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 0.5rem;
        }
        .advantage-desc {
            color: #bdbde6;
            font-size: 0.97rem;
        }
        /* --- FOOTER --- */
        footer {
            background: #181829;
            padding: 2rem 2.5rem 1rem 2.5rem;
            text-align: center;
            color: #bdbde6;
            font-size: 1rem;
            border-top: 1px solid #23234a;
        }
        .footer-links {
            margin-bottom: 1rem;
        }
        .footer-links a {
            margin: 0 1rem;
            color: #8f5fff;
            font-weight: 500;
            transition: color 0.2s;
        }
        .footer-links a:hover {
            color: #3e8eff;
        }
        .footer-social {
            margin-top: 0.5rem;
        }
        .footer-social a {
            margin: 0 0.5rem;
            color: #8f5fff;
            font-size: 1.3rem;
            transition: color 0.2s;
        }
        .footer-social a:hover {
            color: #3e8eff;
        }
        /* --- RESPONSIVE --- */
        @media (max-width: 900px) {
            .hero { flex-direction: column; gap: 2.5rem; }
            .hero-img { margin-top: 2rem; }
            .security-content { flex-direction: column; }
            .features, .advantages { flex-direction: column; }
        }
        @media (max-width: 600px) {
            .navbar { flex-direction: column; gap: 1rem; padding: 1rem; }
            .hero, section { padding: 2rem 1rem; }
            .footer-links { display: block; }
        }
    </style>
</head>
<body>
    
    <div id="loader">
        <div class="lds-ring"><div></div><div></div><div></div><div></div></div>
    </div>
    
    <header class="navbar">
        <div class="logo"><i class="fas fa-shield-alt"></i> SecureHealth</div>
        <nav>
            <a href="#hero">Accueil</a>
            <a href="#features">Fonctionnalités</a>
            <a href="#security">Sécurité</a>
            <a href="#contact">Contact</a>
        </nav>
        <div class="actions">
            <a href="login.php" class="btn btn-outline">Se connecter</a>
            <a href="register.php" class="btn btn-primary">S’inscrire</a>
        </div>
    </header>
    
    <section class="hero" id="hero">
        <div class="hero-content">
            <div class="badge-military"><i class="fas fa-medal"></i> Sécurité niveau militaire 🔒</div>
            <h1 class="hero-title">
                Protégez vos données médicales<br>
                avec une sécurité de niveau avancé <span style="color:#3e8eff;">🔐</span>
            </h1>
            <div class="hero-desc">
                SecureHealth révolutionne la gestion de vos documents médicaux grâce à la triple authentification (3FA) : mot de passe, OTP et biométrie.<br>
                Vos données sensibles n’ont jamais été aussi bien protégées.
            </div>
            <div class="hero-cta">
                <a href="register.php" class="btn btn-primary">Commencer maintenant</a>
                <a href="#features" class="btn btn-outline">En savoir plus</a>
            </div>
        </div>
        <div class="hero-img">
            <img id="hero-img" src="assets/hero_cyber_health.svg" alt="Cybersécurité médicale" style="display:block;">
            <div id="hero-img-fallback" style="display:none;">
                
                <svg width="350" height="250" viewBox="0 0 350 250" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="350" height="250" rx="24" fill="#23234a"/>
                    <circle cx="175" cy="125" r="70" fill="#3e8eff" fill-opacity="0.18"/>
                    <rect x="110" y="90" width="130" height="70" rx="14" fill="#8f5fff" fill-opacity="0.22"/>
                    <path d="M175 110a15 15 0 1 1 0 30a15 15 0 0 1 0-30z" fill="#8f5fff"/>
                    <rect x="160" y="145" width="30" height="8" rx="4" fill="#3e8eff"/>
                    <text x="50%" y="210" text-anchor="middle" fill="#8f5fff" font-size="22" font-family="Montserrat, Arial" font-weight="bold">SecureHealth</text>
                    <text x="50%" y="235" text-anchor="middle" fill="#fff" font-size="14" font-family="Montserrat, Arial">Cybersécurité</text>
                </svg>
            </div>
            <canvas class="scan-effect" width="350" height="350"></canvas>
        </div>
    </section>
    
    <section id="features">
        <h2 class="section-title">Fonctionnalités clés</h2>
        <div class="features">
            <div class="feature-card">
                <i class="fas fa-user-lock"></i>
                <div class="feature-title">Triple authentification</div>
                <div class="feature-desc">Mot de passe, OTP et biométrie pour une sécurité maximale à chaque connexion.</div>
            </div>
            <div class="feature-card">
                <i class="fas fa-file-medical"></i>
                <div class="feature-title">Stockage sécurisé</div>
                <div class="feature-desc">Vos documents médicaux sont chiffrés et stockés dans un espace hautement sécurisé.</div>
            </div>
            <div class="feature-card">
                <i class="fas fa-chart-line"></i>
                <div class="feature-title">Suivi des connexions</div>
                <div class="feature-desc">Consultez l’historique des accès à votre compte et soyez alerté en cas d’activité inhabituelle.</div>
            </div>
            <div class="feature-card">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="feature-title">Détection d’activités suspectes</div>
                <div class="feature-desc">Notre IA détecte et bloque toute tentative d’intrusion ou de phishing.</div>
            </div>
        </div>
    </section>
    
    <section id="security" class="security-section">
        <h2 class="section-title">Pourquoi la triple authentification ?</h2>
        <div class="security-content">
            <div class="security-info">
                <h3>Données médicales = Données ultra sensibles</h3>
                <ul>
                    <li>Informations personnelles et confidentielles</li>
                    <li>Risques de vol d’identité et d’usurpation</li>
                    <li>Respect du secret médical et des réglementations</li>
                </ul>
                <h3>La 3FA, comment ça marche ?</h3>
                <ul>
                    <li>1️⃣ Mot de passe personnel</li>
                    <li>2️⃣ Code OTP envoyé à votre mobile</li>
                    <li>3️⃣ Validation biométrique (empreinte ou reconnaissance faciale)</li>
                </ul>
                <h3>Bien plus sûr qu’un simple mot de passe !</h3>
                <ul>
                    <li>Protection contre le phishing et le vol de mot de passe</li>
                    <li>Accès impossible sans les 3 facteurs</li>
                </ul>
            </div>
            <div class="security-illustration">
                <i class="fas fa-fingerprint"></i>
            </div>
        </div>
    </section>
    
    <section class="demo-section">
        <h2 class="section-title">Phishing ? Pas de panique !</h2>
        <div>
            <p>
                <strong>Le phishing</strong> consiste à voler votre mot de passe.<br>
                <span style="color:#8f5fff;">Avec SecureHealth, même si votre mot de passe est compromis, l’accès à vos données reste impossible sans OTP et biométrie.</span>
            </p>
            <a href="#" class="btn btn-outline" onclick="alert('Démo à intégrer : ici, on montre qu’un mot de passe volé ne suffit pas !');return false;">Voir la démonstration</a>
        </div>
    </section>
    
    <section>
        <h2 class="section-title">Les avantages SecureHealth</h2>
        <div class="advantages">
            <div class="advantage-card">
                <i class="fas fa-lock"></i>
                <div class="advantage-title">Sécurité renforcée</div>
                <div class="advantage-desc">Triple barrière de protection contre toutes les menaces.</div>
            </div>
            <div class="advantage-card">
                <i class="fas fa-user-secret"></i>
                <div class="advantage-title">Confidentialité garantie</div>
                <div class="advantage-desc">Vos données restent strictement privées et chiffrées.</div>
            </div>
            <div class="advantage-card">
                <i class="fas fa-mobile-alt"></i>
                <div class="advantage-title">Facilité d’utilisation</div>
                <div class="advantage-desc">Connexion rapide et intuitive, même avec 3FA.</div>
            </div>
            <div class="advantage-card">
                <i class="fas fa-microchip"></i>
                <div class="advantage-title">Technologie moderne</div>
                <div class="advantage-desc">Infrastructure cloud, IA, biométrie et sécurité de pointe.</div>
            </div>
        </div>
    </section>
    
    <footer id="contact">
        <div class="footer-links">
            <a href="#hero">Accueil</a> |
            <a href="#features">Fonctionnalités</a> |
            <a href="#security">Sécurité</a> |
            <a href="mailto:contact@securehealth.com">Contact</a>
        </div>
        <div class="footer-social">
            <a href="#"><i class="fab fa-linkedin"></i></a>
            <a href="#"><i class="fab fa-twitter"></i></a>
            <a href="#"><i class="fab fa-github"></i></a>
        </div>
        <div style="margin-top:1rem;font-size:0.95rem;">
            &copy; <?php echo date('Y'); ?> SecureHealth. Tous droits réservés.
        </div>
    </footer>
    
    <script>
        // Loader
        window.addEventListener('load', function() {
            document.getElementById('loader').style.opacity = 0;
            setTimeout(() => document.getElementById('loader').style.display = 'none', 500);
        });

        // Apparition progressive des cards au scroll
        function revealOnScroll(selector) {
            const cards = document.querySelectorAll(selector);
            function reveal() {
                const trigger = window.innerHeight * 0.92;
                cards.forEach(card => {
                    const top = card.getBoundingClientRect().top;
                    if (top < trigger) card.classList.add('visible');
                });
            }
            window.addEventListener('scroll', reveal);
            reveal();
        }
        revealOnScroll('.feature-card');
        revealOnScroll('.advantage-card');

        // Effet scan de sécurité sur le hero
        function scanEffect() {
            const canvas = document.querySelector('.scan-effect');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            let y = 0, direction = 1;
            function draw() {
                ctx.clearRect(0,0,canvas.width,canvas.height);
                ctx.globalAlpha = 0.18;
                ctx.fillStyle = '#3e8eff';
                ctx.fillRect(0, y, canvas.width, 30);
                ctx.globalAlpha = 1;
                y += direction * 2;
                if (y > canvas.height-30 || y < 0) direction *= -1;
                requestAnimationFrame(draw);
            }
            draw();
        }
        scanEffect();

        // Fallback local SVG si l'image ne charge pas
        document.getElementById('hero-img').addEventListener('error', function() {
            this.style.display = 'none';
            document.getElementById('hero-img-fallback').style.display = 'block';
        });
    </script>
</body>
</html>
