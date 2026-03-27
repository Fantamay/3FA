<?php

$stolen = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stolen = true;
    $stolen_email = htmlspecialchars($_POST['email']);
    $stolen_pass = htmlspecialchars($_POST['password']);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Démo Attaque Phishing</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container">
    <h1>⚠️ Simulation d'attaque Phishing</h1>
    <?php if ($stolen): ?>
        <div class="message" style="background:#ff2d2d;color:#fff;">
            <b>Mot de passe volé !</b><br>
            Email : <?= $stolen_email ?><br>
            Mot de passe : <?= $stolen_pass ?><br>
            <hr>
            <b>Explication :</b>  
            Sur un vrai site malveillant, vos identifiants seraient envoyés à un pirate.<br>
            <b>La triple authentification protège :</b>  
            Même si le mot de passe est volé, il manque l’OTP et le PIN/biométrie pour accéder à vos données sensibles.
        </div>
    <?php endif; ?>
    <form method="post" action="">
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Mot de passe" required>
        <button type="submit">Connexion</button>
    </form>
    <div class="message" style="background:#23234a;color:#8f5fff;">
        <b>Conseil :</b>  
        Vérifiez toujours l’URL avant de saisir vos identifiants !
    </div>
    <a href="login.php"><button style="background:#23234a;">Retour à l'app</button></a>
</div>
</body>
</html>
