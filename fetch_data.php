<?php
$host = "votre_ip";  // L'adresse IP de votre serveur PostgreSQL
$dbname = "votre_base_de_donnees";
$user = "votre_utilisateur";
$password = "votre_mot_de_passe";

try {
    $conn = new PDO("pgsql:host=$host;dbname=$dbname", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $conn->query("SELECT * FROM res_partner"); // Remplacez par votre requête
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}

?>
