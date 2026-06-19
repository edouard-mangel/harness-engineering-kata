# Kata d'ingénierie du harnais

Expérimentez le réglage et l'outillage du harnais des agents pour obtenir le résultat souhaité.

Implémentez la même fonctionnalité encore et encore, améliorez le harnais pour que l'agent génère des versions de plus en plus bonnes.

Par harnais, on entend ici tout ce qui influence le comportement de l'agent et les mécanismes de retour mis en place :
- le fichier AGENTS.md / CLAUDE.md
- les skills
- les scripts (pour rendre les résultats prévisibles)
- les documents d'architecture et les contraintes (comme ArchUnit)
- les descriptions de processus / workflow
- le README.md et les autres fichiers visibles à la racine du dépôt


## Prérequis

Installez les outils de vérification de qualité (nécessaires pour les étapes 6-7) :

```bash
# Python
pip install -r python/requirements-dev.txt

# PHP
cd php && composer install && cd ..

# TypeScript
cd typescript && pnpm install && cd ..

# Java — ajoutez le plugin checkstyle à pom.xml (voir "Outils qualité par langage" ci-dessous)
```

## Claude Code (optionnel)

Pour une instance Claude Code « fraîche », définissez un nouveau répertoire de configuration :
```bash
export CLAUDE_CONFIG_DIR=~/.claude_hek
```
_CLAUDE_CONFIG_DIR remplace le répertoire de configuration (par défaut : \~/.claude). Tous les paramètres, identifiants, historique de session et plugins sont stockés sous ce chemin. Utile pour utiliser plusieurs comptes en parallèle : par exemple, `alias claude-work='CLAUDE_CONFIG_DIR=~/.claude-work claude'`_ https://code.claude.com/docs/en/env-vars


## Étapes
Pour chaque étape, supprimez tout le code et revenez à main, puis utilisez cette invite, sauf instruction contraire :

    implement the feature from feature.md

Les étapes 6 et 7 introduisent des changements de configuration (fichiers de config, scripts de hook) qui doivent persister entre les itérations tandis que le code généré par l'agent est supprimé. Le flux de travail est le suivant : exécutez les commandes `cp`, **commitez et taguez la configuration**, puis lancez l'invite. Entre les étapes, faites un hard-reset vers le commit de configuration tagué — cela supprime le code généré tout en conservant le harnais en place.

### 1. Sans harnais

  Démarrez sans aucun fichier de harnais, et ne créez pas de AGENTS.md ou CLAUDE.md.

### 2. Ajouter un fichier d'instruction minimal

  Créez AGENTS.md ou CLAUDE.md et ajoutez une instruction simple telle que : « Add full test coverage for new features. »

### 3. Ajouter une protection contre les régressions

  En supposant que l'agent peut sauter les tests pour le code existant non testé, ajoutez une instruction comme : « To protect against regressions, always add full coverage for existing code before modifying it. »

### 4. Refactoriser jusqu'à une qualité acceptable

  En supposant que la qualité initiale du code est faible, demandez à l'agent de refactoriser de manière répétée jusqu'à satisfaction, puis demandez-lui d'extraire les principes de conception de la conversation dans un fichier tel que `docs/design-principles.md`.

  Commitez les principes de conception, mais revenez en arrière sur l'implémentation et les tests avant de passer à l'étape suivante.

### 5. Réutiliser les principes de conception et recommencer

  Référencez `docs/design-principles.md` depuis AGENTS.md ou CLAUDE.md, puis repartez de zéro et comparez si le code résultant est similaire au résultat amélioré de la première itération.

### 6. Ajouter une porte qualité via un hook Stop (agent revieweur)

  Activez un hook Stop qui lance un sous-agent revieweur après chaque tour de l'agent. Le revieweur exécute le script de vérification qualité, lit le code en infraction et décide de bloquer ou d'autoriser.

  Si vous utilisez Copilot ou OpenCode, vous pouvez remplacer le hook Stop par un hook pre-commit et demander à l'agent de commiter pour provoquer la boucle de retour. Demandez à votre agent de mettre cela en place et de vérifier que c'est bien déclenché sur un changement factice.

  ```bash
  cp harness/step-6/settings.json .claude/settings.json
  cp harness/step-6/python/.flake8 python/.flake8
  cp harness/step-6/java/checkstyle.xml java/checkstyle.xml
  cp harness/step-6/php/phpmd.xml php/phpmd.xml
  cp harness/step-6/typescript/.eslintrc.json typescript/.eslintrc.json
  mkdir -p .claude/hooks
  cp harness/step-6/.claude/hooks/*.sh .claude/hooks/
  chmod +x .claude/hooks/*.sh

  git add -A && git commit -m "step 6 setup"
  git tag -f step-6-setup
  ```

  Redémarrez (quittez) votre session Claude Code / Copilot, puis lancez l'invite.

### 7. Ajouter une porte qualité mécanique via un hook Stop (blocage dur)

  Remplacez l'agent revieweur par une porte qualité déterministe qui effectue les mêmes vérifications qu'à l'étape 6, mais les impose via le code de sortie 2 plutôt que par le jugement de l'agent. Toute violation empêche l'agent de terminer, le forçant à itérer jusqu'à ce que le code soit propre. C'est la technique la plus efficace identifiée dans l'expérience sur les portes qualité (voir [Discussion](#discussion)).

  Repartez de la configuration de l'étape 6 — cela supprime le code généré par l'agent à l'étape 6 mais conserve le harnais (`.flake8`, `checkstyle.xml`, scripts de hook) en place :

  ```bash
  git reset --hard step-6-setup

  cp harness/step-7/settings.json .claude/settings.json

  git add -A && git commit -m "step 7 setup"
  git tag -f step-7-setup
  ```

  Redémarrez votre session Claude Code / Copilot, puis lancez l'invite.

### 8. Ajouter un skill TDD par recherche et réapplication

  Demandez à l'agent de rechercher ce qu'est le TDD et comment il s'applique aux workflows d'agents, de l'expliquer clairement, de créer un skill TDD dédié, puis de réimplémenter la fonctionnalité en utilisant ce skill.

### 9. Ajouter une capacité de débogage et extraire un skill

  Permettez à l'agent d'exécuter l'application en mode debug, introduisez un bug, demandez à l'agent de le diagnostiquer et de le corriger, puis extrayez ce workflow de débogage dans un skill réutilisable ajouté au dépôt.


## Exécution

### Java

```bash
cd java
mvn -q compile
java -cp target/classes com.kata.warehouse.Main
```

### Python

```bash
cd python
python main.py
```

### PHP

```bash
cd php
composer install
php main.php
```

### TypeScript

```bash
cd typescript
pnpm install
pnpm start
```


## Outils qualité par langage

### Python

Configuré dans `harness/step-6/.claude/hooks/check-quality.sh`. Vérifications : longueur de fonction (30), complexité cognitive (10), nombres magiques, suremploi de constantes chaînes, attributs d'instance de classe (6), longueur de fichier (150 lignes), nombre maximum de paramètres (4). La config flake8 se trouve dans `harness/step-6/python/.flake8`. Les deux sont copiés en place lors de l'activation de l'étape 6 (voir instructions étape 6 ci-dessus).

### PHP

Utilise [PHPMD](https://phpmd.org/) (PHP Mess Detector). Vérifications : complexité cyclomatique (10), longueur de méthode (30 lignes), longueur de classe (150 lignes), attributs de classe (6), nombre de paramètres (4). Le jeu de règles se trouve dans `harness/step-6/php/phpmd.xml` — copié dans `php/phpmd.xml` lors de l'activation de l'étape 6. Nécessite `composer install` dans `php/`.

### TypeScript

Utilise [ESLint](https://eslint.org/) avec `@typescript-eslint`. Vérifications : complexité cyclomatique (10), longueur de fonction (30 lignes), longueur de fichier (150 lignes), nombre de paramètres (4). La config se trouve dans `harness/step-6/typescript/.eslintrc.json` — copiée dans `typescript/.eslintrc.json` lors de l'activation de l'étape 6. Nécessite `pnpm install` dans `typescript/`.

### Java

Utilisez [Checkstyle](https://checkstyle.org/) pour les mêmes vérifications. Un `checkstyle.xml` de départ couvrant la longueur des méthodes, le nombre de paramètres, la complexité cyclomatique, les nombres magiques, les littéraux chaînes et la longueur de fichier se trouve dans `harness/step-6/java/checkstyle.xml` — copié en place lors de l'activation de l'étape 6. Ajoutez le plugin maven-checkstyle-plugin à `pom.xml` et adaptez `harness/step-6/.claude/hooks/check-quality.sh` pour qu'il appelle aussi `mvn -q checkstyle:check`.

[ArchUnit](https://www.archunit.org/) peut en outre imposer des contraintes architecturales (dépendances entre packages, séparation des couches) sous forme de tests exécutables.


## Discussion

### Expérience sur les portes qualité

Une expérience a comparé 6 techniques de harnais pour amener les agents IA à refactoriser de manière proactive, sur 3 cycles de rigueur croissante. Résultats complets : [`docs/experiment-summary.md`](docs/experiment-summary.md).

**Techniques testées :** injection de contexte douce (hook UserPromptSubmit), hooks PreToolUse basés sur `ask` (script shell et agent LLM), hooks Stop à blocage dur (script shell et agent revieweur LLM), et application différée (journal des violations en pre-commit).

**Résultat clé :** Le blocage dur mécanique (hook Stop avec code de sortie 2) est la seule technique qui fonctionne de manière fiable à l'échelle. Les techniques douces (injection de contexte, journalisation différée) ont été systématiquement ignorées. Les revieweurs LLM ont partiellement fonctionné, mais ont exercé un jugement qui a parfois laissé passer des violations.

**Conclusion :** L'agent n'a pas besoin de comprendre *pourquoi* il devrait refactoriser. Il doit être mécaniquement empêché de terminer jusqu'à ce que le code soit propre. La compréhension est optionnelle ; l'application est obligatoire.
