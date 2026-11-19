<?php
// Page d'ajout : un seul formulaire pour ajouter un document ou un favori
require_once "includes/fonctions.php";

$erreurs = [];

// valeurs de depart du formulaire
$type = "document";
$titre = "";
$motsCles = "";
$description = "";
$url = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (requeteTropVolumineuse()) {
        // si le fichier depasse post_max_size, PHP vide $_POST et $_FILES
        $erreurs[] = "Les données envoyées dépassent la taille maximale acceptée par le serveur ("
            . ini_get("post_max_size") . "). Choisissez un fichier plus léger.";
    } else {

        // on recupere les saisies pour pouvoir les remettre dans le formulaire si erreur
        $type = lireChampPost("type");
        $titre = lireChampPost("titre");
        $motsCles = lireChampPost("mots-cles");
        $description = lireChampPost("description");
        $url = lireChampPost("url");
        $fichier = $_FILES["fichier"] ?? null;
        $fichierOk = false;

        // verification du titre
        if (nettoyerTexte($titre) === "") {
            $erreurs[] = "Le titre est obligatoire.";
        } elseif (mb_strlen($titre) > 150) {
            $erreurs[] = "Le titre ne doit pas dépasser 150 caractères.";
        }

        // verification du type, puis du fichier ou de l'url
        if ($type != "document" && $type != "favori") {
            $erreurs[] = "Le type de ressource est invalide : choisissez « Document » ou « Favori ».";
            $type = "document";
        } elseif ($type == "document") {
            $erreurFichier = verifierFichier($fichier);
            if ($erreurFichier === null) {
                $fichierOk = true;
            } else {
                $erreurs[] = $erreurFichier;
            }
        } else {
            if ($url === "") {
                $erreurs[] = "L'adresse (URL) est obligatoire pour un favori.";
            } elseif (!urlValide($url)) {
                $erreurs[] = "L'adresse doit commencer par http:// ou https:// et ne contenir "
                    . "ni espace ni guillemet (ex : https://www.php.net).";
            }
        }

        if (!is_writable(FICHIER_GED)) {
            $erreurs[] = "Enregistrement impossible : le fichier " . FICHIER_GED . " n'est pas accessible en écriture.";
        }

        // pas d'erreur : on enregistre et on retourne sur la liste
        if (empty($erreurs)) {

            // nettoyerTexte() remplace les " par &quot; donc on previent l'utilisateur
            $guillemets = str_contains($titre . $motsCles . $description, '"');

            // l'id est ajoute par la fonction ajouter()
            $ressource = [
                "type" => $type,
                "titre" => nettoyerTexte($titre),
                "mots-cles" => nettoyerTexte(formaterMotsCles($motsCles)),
                "description" => nettoyerTexte($description, true),
            ];

            if ($type == "document") {
                $nomFichier = enregistrerFichier($fichier);
                if ($nomFichier === null) {
                    $erreurs[] = "Le fichier n'a pas pu être enregistré dans le dossier uploads/.";
                } else {
                    $ressource["fichier"] = $nomFichier;
                }
            } else {
                $ressource["url"] = nettoyerTexte($url);
            }
            $ressource["date"] = date("Y-m-d");

            if (empty($erreurs)) {
                ajouter($ressource);

                $retour = "index.php?message=ajout";
                if ($guillemets) {
                    $retour .= "&guillemets=1";
                }
                header("Location: " . $retour);
                exit;
            }
        }

        // le navigateur ne garde pas le fichier choisi quand la page se recharge
        if (!empty($erreurs) && $type == "document" && $fichierOk) {
            $erreurs[] = "Par sécurité, le navigateur ne conserve pas le fichier choisi : sélectionnez-le à nouveau.";
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MediaBox - Ajouter une ressource</title>
    <link rel="stylesheet" href="style/style.css">
</head>
<body>
    <header class="entete">
        <div class="entete-contenu">
            <a class="logo" href="index.php">
                <span class="logo-icone" aria-hidden="true">🎵</span>
                <span><strong>MediaBox</strong><small>Le partage musical du club</small></span>
            </a>
            <a class="bouton bouton-clair" href="index.php">← Retour à la liste</a>
        </div>
    </header>

    <main class="etroit">
        <div class="carte">
            <h1>Ajouter une ressource</h1>
            <p class="aide">Les champs avec une <span class="obligatoire">*</span> sont obligatoires.</p>

            <?php if (!empty($erreurs)): ?>
                <div class="message message-erreur" role="alert">
                    <p><strong>Le formulaire n'a pas été enregistré :</strong></p>
                    <ul>
                        <?php foreach ($erreurs as $erreur): ?>
                            <li><?= echapper($erreur) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- enctype obligatoire pour envoyer un fichier -->
            <form method="post" action="ajouter.php" enctype="multipart/form-data">

                <fieldset class="choix-type">
                    <legend>Type de ressource <span class="obligatoire">*</span></legend>
                    <div class="options-type">
                        <label class="option-type">
                            <input type="radio" name="type" value="document" required
                                <?= $type == "document" ? "checked" : "" ?>>
                            <span><span aria-hidden="true">📄</span> Document
                                <small>Morceau MP3, pochette, partition, analyse...</small></span>
                        </label>
                        <label class="option-type">
                            <input type="radio" name="type" value="favori"
                                <?= $type == "favori" ? "checked" : "" ?>>
                            <span><span aria-hidden="true">🔗</span> Favori
                                <small>Lien YouTube, site, plateforme d'écoute...</small></span>
                        </label>
                    </div>
                </fieldset>

                <div class="champ">
                    <label for="titre">Titre <span class="obligatoire">*</span></label>
                    <input type="text" id="titre" name="titre" maxlength="150" required
                           value="<?= echapper($titre) ?>">
                </div>

                <!-- affiche si on choisit Document -->
                <div class="champ" id="bloc-fichier">
                    <label for="fichier">Fichier <span class="obligatoire">*</span></label>
                    <input type="file" id="fichier" name="fichier" aria-describedby="aide-fichier"
                           accept=".<?= implode(",.", EXTENSIONS_AUTORISEES) ?>">
                    <p class="aide" id="aide-fichier">
                        Formats acceptés : <?= implode(", ", EXTENSIONS_AUTORISEES) ?>
                        (taille maximale : <?= echapper(ini_get("upload_max_filesize")) ?>).
                    </p>
                </div>

                <!-- affiche si on choisit Favori -->
                <div class="champ" id="bloc-url">
                    <label for="url">Adresse du site (URL) <span class="obligatoire">*</span></label>
                    <input type="url" id="url" name="url" placeholder="https://..."
                           value="<?= echapper($url) ?>">
                </div>

                <div class="champ">
                    <label for="mots-cles">Mots-clés</label>
                    <input type="text" id="mots-cles" name="mots-cles" aria-describedby="aide-mots-cles"
                           value="<?= echapper($motsCles) ?>">
                    <p class="aide" id="aide-mots-cles">Séparés par des virgules, par exemple : rock, guitare, démo</p>
                </div>

                <div class="champ">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="5"><?= echapper($description) ?></textarea>
                </div>

                <div class="boutons-formulaire">
                    <button type="submit" class="bouton">Enregistrer</button>
                    <a class="bouton bouton-secondaire" href="index.php">Annuler</a>
                </div>
            </form>
        </div>
    </main>

    <footer class="pied">
        <p>MediaBox - prototype de GED pour OrgaPlus (club musical) - données dans data/ressources.txt</p>
    </footer>

    <script>
        // Affiche le champ fichier OU le champ url selon le type coche
        const boutonsType = document.querySelectorAll('input[name="type"]');
        const blocFichier = document.getElementById("bloc-fichier");
        const blocUrl = document.getElementById("bloc-url");

        function basculerFormulaire() {
            const estDocument = document.querySelector('input[name="type"]:checked').value === "document";
            blocFichier.hidden = !estDocument;
            blocUrl.hidden = estDocument;
            // un champ desactive n'est pas envoye avec le formulaire
            document.getElementById("fichier").disabled = !estDocument;
            document.getElementById("url").disabled = estDocument;
        }

        boutonsType.forEach(function (bouton) {
            bouton.addEventListener("change", basculerFormulaire);
        });

        // au chargement aussi (par exemple quand la page revient avec des erreurs)
        basculerFormulaire();
    </script>
</body>
</html>
