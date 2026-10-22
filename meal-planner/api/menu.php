<?php
check_auth();
$userId = get_current_user_id();

class GeneticMenuOptimizer {
    private $pdo;
    private $userId;
    
    private $populationSize = 50;
    private $generations = 100;
    private $mutationRate = 0.05;
    private $crossoverRate = 0.8;
    private $eliteCount = 5;
    
    private $targetCalories;
    private $targetProtein;
    private $targetFat;
    private $targetCarbs;
    private $maxCookingTimePerDay = 120;
    
    private $periodStart;
    private $periodEnd;
    private $daysCount;
    private $mealsPerDay;
    
    private $dishesPerMealByType = [];
    private $drinksPerMealByType = [];
    private $mealTypesOrdered = [];
    private $totalDishGenes = 0;
    private $totalDrinkGenes = 0;
    
    private $allRecipes = [];
    private $allDrinks = [];
    private $favoriteRecipes = [];
    private $recommendedRecipes = [];
    private $recipesCache = [];
    private $ingredientsCache = [];
    
    private $allergens = [];
    private $excludedProducts = [];
    private $diets = [];
    private $maxDifficulty;
    private $preferredCuisine;
    
    public function __construct($pdo, $userId, $params) {
        $this->pdo = $pdo;
        $this->userId = $userId;
        
        $this->periodStart = $params['period_start'] ?? date('Y-m-d', strtotime('monday this week'));
        $this->periodEnd   = $params['period_end']   ?? date('Y-m-d', strtotime('sunday this week'));
        $this->daysCount = (strtotime($this->periodEnd) - strtotime($this->periodStart)) / 86400 + 1;
        $this->mealsPerDay = (int)($params['meals_per_day'] ?? 3);
        
        $mealTypesAll = ['breakfast', 'lunch', 'dinner', 'snack'];
        $this->mealTypesOrdered = array_slice($mealTypesAll, 0, $this->mealsPerDay);
        
        $defaultDishes = (int)($params['dishes_per_meal'] ?? 1);
        $defaultDrinks = (int)($params['drinks_per_meal'] ?? 1);
        
        foreach ($this->mealTypesOrdered as $mt) {
            $this->dishesPerMealByType[$mt] = (int)($params["dishes_{$mt}"] ?? $defaultDishes);
            $this->drinksPerMealByType[$mt] = (int)($params["drinks_{$mt}"] ?? $defaultDrinks);
        }
        
        $this->totalDishGenes = array_sum($this->dishesPerMealByType);
        $this->totalDrinkGenes = array_sum($this->drinksPerMealByType);
        
        $this->loadTargets();
        $this->loadRestrictionsAndPreferences();
        $this->loadRecipesAndDrinks();
        $this->loadFavorites();
        $this->loadRecommendations();
    }
    
    private function loadTargets() {
        $stmt = $this->pdo->prepare("
            SELECT daily_calories_goal, daily_protein_goal, daily_fat_goal, daily_carbs_goal
            FROM anthropometry WHERE user_id = ? ORDER BY record_date DESC LIMIT 1
        ");
        $stmt->execute([$this->userId]);
        $targets = $stmt->fetch();
        if (!$targets) {
            throw new Exception('Антропометрические данные не заполнены');
        }
        $this->targetCalories = (int)$targets['daily_calories_goal'];
        $this->targetProtein  = (float)$targets['daily_protein_goal'];
        $this->targetFat      = (float)$targets['daily_fat_goal'];
        $this->targetCarbs    = (float)$targets['daily_carbs_goal'];
    }
    
    private function loadRestrictionsAndPreferences() {
        $stmt = $this->pdo->prepare("SELECT allergen_type FROM user_allergens WHERE user_id = ?");
        $stmt->execute([$this->userId]);
        $this->allergens = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $stmt = $this->pdo->prepare("SELECT excluded_product_name FROM user_excluded_products WHERE user_id = ?");
        $stmt->execute([$this->userId]);
        $this->excludedProducts = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $stmt = $this->pdo->prepare("SELECT restriction_type FROM user_dietary_restrictions WHERE user_id = ?");
        $stmt->execute([$this->userId]);
        $this->diets = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $stmt = $this->pdo->prepare("SELECT max_difficulty, preferred_cuisine FROM user_preferences_core WHERE user_id = ?");
        $stmt->execute([$this->userId]);
        $prefs = $stmt->fetch();
        $this->maxDifficulty = $prefs['max_difficulty'] ?? 2;
        $this->preferredCuisine = $prefs['preferred_cuisine'] ?? 'any';
    }
    
    private function loadRecipesAndDrinks() {
        $stmt = $this->pdo->prepare("
            SELECT recipe_id, name, category_id, cooking_time, difficulty,
                   calories_per_serving, protein_per_serving, fat_per_serving, carbs_per_serving,
                   cuisine, image_url
            FROM recipes WHERE is_drink = 0
        ");
        $stmt->execute();
        $this->allRecipes = $stmt->fetchAll();
        foreach ($this->allRecipes as $r) {
            $this->recipesCache[$r['recipe_id']] = $r;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT recipe_id, name, category_id, cooking_time, difficulty,
                   calories_per_serving, protein_per_serving, fat_per_serving, carbs_per_serving,
                   cuisine, image_url
            FROM recipes WHERE is_drink = 1
        ");
        $stmt->execute();
        $this->allDrinks = $stmt->fetchAll();
        foreach ($this->allDrinks as $d) {
            $this->recipesCache[$d['recipe_id']] = $d;
        }
    }
    
    private function loadFavorites() {
        $stmt = $this->pdo->prepare("SELECT recipe_id FROM user_favorites WHERE user_id = ?");
        $stmt->execute([$this->userId]);
        $this->favoriteRecipes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    private function loadRecommendations() {
        $this->recommendedRecipes = $this->favoriteRecipes;
    }
    
    private function getWeightedRecipes($isDrink) {
        $recipes = $isDrink ? $this->allDrinks : $this->allRecipes;
        $weighted = [];
        foreach ($recipes as $r) {
            if (!$this->isRecipeAllowed($r)) continue;
            $weight = in_array($r['recipe_id'], $this->favoriteRecipes) ? 10 : 1;
            for ($i = 0; $i < $weight; $i++) {
                $weighted[] = $r;
            }
        }
        return $weighted;
    }
    
    private function isRecipeAllowed($recipe) {
        if ($recipe['difficulty'] > $this->maxDifficulty) return false;
        
        $ingredients = $this->getRecipeIngredients($recipe['recipe_id']);
        
        foreach ($this->allergens as $allergen) {
            if (in_array($allergen, $ingredients)) return false;
        }
        foreach ($this->excludedProducts as $excluded) {
            if (in_array($excluded, $ingredients)) return false;
        }
        
        if (in_array('vegan', $this->diets)) {
            $forbidden = ['Мясо', 'Молочные', 'Яйца', 'Рыба'];
            $cats = $this->getRecipeIngredientCategories($recipe['recipe_id']);
            if (array_intersect($cats, $forbidden)) return false;
        }
        if (in_array('vegetarian', $this->diets)) {
            $forbidden = ['Мясо', 'Рыба'];
            $cats = $this->getRecipeIngredientCategories($recipe['recipe_id']);
            if (array_intersect($cats, $forbidden)) return false;
        }
        if (in_array('gluten_free', $this->diets)) {
            $gluten = ['Мука пшеничная', 'Пшеница', 'Ячмень', 'Рожь', 'Овёс'];
            if (array_intersect($gluten, $ingredients)) return false;
        }
        if (in_array('dairy_free', $this->diets)) {
            $dairy = ['Молоко', 'Сливки', 'Сметана', 'Творог', 'Сыр', 'Масло сливочное', 'Йогурт', 'Кефир'];
            if (array_intersect($dairy, $ingredients)) return false;
        }
        return true;
    }
    
    private function getRecipeIngredients($recipeId) {
        if (isset($this->ingredientsCache[$recipeId]['names'])) {
            return $this->ingredientsCache[$recipeId]['names'];
        }
        $stmt = $this->pdo->prepare("
            SELECT i.name FROM recipe_ingredients ri
            JOIN ingredients i ON ri.ingredient_id = i.ingredient_id
            WHERE ri.recipe_id = ?
        ");
        $stmt->execute([$recipeId]);
        $names = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->ingredientsCache[$recipeId]['names'] = $names;
        return $names;
    }
    
    private function getRecipeIngredientCategories($recipeId) {
        if (isset($this->ingredientsCache[$recipeId]['categories'])) {
            return $this->ingredientsCache[$recipeId]['categories'];
        }
        $stmt = $this->pdo->prepare("
            SELECT i.category FROM recipe_ingredients ri
            JOIN ingredients i ON ri.ingredient_id = i.ingredient_id
            WHERE ri.recipe_id = ?
        ");
        $stmt->execute([$recipeId]);
        $cats = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->ingredientsCache[$recipeId]['categories'] = $cats;
        return $cats;
    }
    
    private function getGeneInfo($geneIndex) {
        $totalDishes = $this->totalDishGenes;
        if ($geneIndex < $totalDishes) {
            $idx = $geneIndex;
            foreach ($this->mealTypesOrdered as $mt) {
                $cnt = $this->dishesPerMealByType[$mt];
                if ($idx < $cnt) {
                    return ['mealType' => $mt, 'isDrink' => false];
                }
                $idx -= $cnt;
            }
        } else {
            $idx = $geneIndex - $totalDishes;
            foreach ($this->mealTypesOrdered as $mt) {
                $cnt = $this->drinksPerMealByType[$mt];
                if ($idx < $cnt) {
                    return ['mealType' => $mt, 'isDrink' => true];
                }
                $idx -= $cnt;
            }
        }
        return ['mealType' => $this->mealTypesOrdered[0], 'isDrink' => false];
    }
    
    private function randomDayChromosome() {
        $chromosome = [];
        foreach ($this->mealTypesOrdered as $mealType) {
            for ($d = 0; $d < $this->dishesPerMealByType[$mealType]; $d++) {
                $allowed = $this->getWeightedRecipes(false);
                $chromosome[] = empty($allowed) ? 0 : $allowed[array_rand($allowed)]['recipe_id'];
            }
        }
        foreach ($this->mealTypesOrdered as $mealType) {
            for ($d = 0; $d < $this->drinksPerMealByType[$mealType]; $d++) {
                if (mt_rand(1,100) <= 70) {
                    $allowed = $this->getWeightedRecipes(true);
                    $chromosome[] = empty($allowed) ? 0 : $allowed[array_rand($allowed)]['recipe_id'];
                } else {
                    $chromosome[] = 0;
                }
            }
        }
        return $chromosome;
    }
    
    private function randomWeekChromosome() {
        $week = [];
        for ($day = 0; $day < $this->daysCount; $day++) {
            $week[] = $this->randomDayChromosome();
        }
        return $week;
    }
    
    private function getDayNutrition($dayChromosome) {
        $nut = ['calories'=>0, 'protein'=>0, 'fat'=>0, 'carbs'=>0, 'time'=>0];
        foreach ($dayChromosome as $gene) {
            if ($gene == 0) continue;
            if (isset($this->recipesCache[$gene])) {
                $r = $this->recipesCache[$gene];
                $nut['calories'] += $r['calories_per_serving'];
                $nut['protein']  += $r['protein_per_serving'];
                $nut['fat']      += $r['fat_per_serving'];
                $nut['carbs']    += $r['carbs_per_serving'];
                $nut['time']     += $r['cooking_time'];
            }
        }
        return $nut;
    }
    
    private function calculateFitness($weekChromosome) {
        $allIds = [];
        foreach ($weekChromosome as $day) {
            foreach ($day as $gene) {
                if ($gene != 0) $allIds[] = $gene;
            }
        }
        $freq = array_count_values($allIds);
        foreach ($freq as $cnt) {
            if ($cnt > 3) return 0;
        }
        
        foreach ($weekChromosome as $dayChrom) {
            $nut = $this->getDayNutrition($dayChrom);
            if ($nut['calories'] < 900 || $nut['calories'] > 4000) return 0;
        }
        
        static $goalType = null;
        if ($goalType === null) {
            $stmt = $this->pdo->prepare("SELECT goal_type FROM anthropometry WHERE user_id = ? ORDER BY record_date DESC LIMIT 1");
            $stmt->execute([$this->userId]);
            $goal = $stmt->fetch();
            $goalType = $goal['goal_type'] ?? 'maintenance';
        }
        
        switch ($goalType) {
            case 'weight_loss': $w1=0.6; $w2=0.2; $w3=0.2; break;
            case 'muscle_gain': $w1=0.5; $w2=0.2; $w3=0.3; break;
            default:            $w1=0.4; $w2=0.3; $w3=0.3;
        }
        
        $balance    = $this->calculateBalance($weekChromosome);
        $variety    = $this->calculateVariety($weekChromosome);
        $preference = $this->calculatePreference($weekChromosome);
        
        $timePenalty = 1;
        foreach ($weekChromosome as $day) {
            $nut = $this->getDayNutrition($day);
            if ($nut['time'] > $this->maxCookingTimePerDay) $timePenalty *= 0.8;
        }
        
        $totalCal = 0;
        foreach ($weekChromosome as $day) {
            $nut = $this->getDayNutrition($day);
            $totalCal += $nut['calories'];
        }
        $avgCal = $totalCal / $this->daysCount;
        $calDev = abs($avgCal - $this->targetCalories) / $this->targetCalories;
        $calPenalty = max(0, 1 - $calDev);
        
        $fitness = $w1 * $balance * $calPenalty + $w2 * $variety + $w3 * $preference;
        return min(1, max(0, $fitness * $timePenalty));
    }
    
    private function calculateBalance($weekChromosome) {
        $totalCal = 0; $totalProt = 0; $totalFat = 0; $totalCarbs = 0;
        foreach ($weekChromosome as $day) {
            $nut = $this->getDayNutrition($day);
            $totalCal += $nut['calories'];
            $totalProt += $nut['protein'];
            $totalFat += $nut['fat'];
            $totalCarbs += $nut['carbs'];
        }
        if ($totalCal == 0) return 0;
        $actualProt = ($totalProt * 4) / $totalCal;
        $actualFat  = ($totalFat * 9) / $totalCal;
        $actualCarbs= ($totalCarbs * 4) / $totalCal;
        $targetProt = ($this->targetProtein * 4) / $this->targetCalories;
        $targetFat  = ($this->targetFat * 9) / $this->targetCalories;
        $targetCarbs= ($this->targetCarbs * 4) / $this->targetCalories;
        $deltaPr = min(1, abs($actualProt - $targetProt) / 0.2);
        $deltaF  = min(1, abs($actualFat - $targetFat) / 0.3);
        $deltaCh = min(1, abs($actualCarbs - $targetCarbs) / 0.4);
        return 1 - ($deltaPr + $deltaF + $deltaCh) / 3;
    }
    
    private function calculateVariety($weekChromosome) {
        $allIds = [];
        foreach ($weekChromosome as $day) {
            foreach ($day as $gene) {
                if ($gene != 0) $allIds[] = $gene;
            }
        }
        $total = count($allIds);
        if ($total == 0) return 0;
        $freq = array_count_values($allIds);
        $penalty = 0;
        foreach ($freq as $cnt) {
            if ($cnt > 3) $penalty += ($cnt - 3);
        }
        $maxPenalty = $total;
        return 1 - min(1, $penalty / max($total,1));
    }
    
    private function calculatePreference($weekChromosome) {
        $totalScore = 0; $totalGenes = 0;
        foreach ($weekChromosome as $day) {
            foreach ($day as $gene) {
                if ($gene == 0) continue;
                $totalGenes++;
                $score = 0;
                if (in_array($gene, $this->favoriteRecipes)) $score += 2;
                if (in_array($gene, $this->recommendedRecipes)) $score += 1;
                if (isset($this->recipesCache[$gene]['cuisine']) &&
                    $this->recipesCache[$gene]['cuisine'] == $this->preferredCuisine) $score += 1;
                $totalScore += $score;
            }
        }
        $maxPossible = $totalGenes * 4;
        return $maxPossible > 0 ? $totalScore / $maxPossible : 0;
    }
    
    private function tournamentSelection($population, $fitnesses) {
        $selected = [];
        $size = count($population);
        for ($i=0; $i<$size; $i++) {
            $a = mt_rand(0, $size-1);
            $b = mt_rand(0, $size-1);
            $selected[] = ($fitnesses[$a] > $fitnesses[$b]) ? $population[$a] : $population[$b];
        }
        return $selected;
    }
    
    private function crossover($parent1, $parent2) {
        if (mt_rand() / mt_getrandmax() > $this->crossoverRate) {
            return [$parent1, $parent2];
        }
        $point = mt_rand(1, count($parent1)-1);
        $child1 = array_merge(array_slice($parent1, 0, $point), array_slice($parent2, $point));
        $child2 = array_merge(array_slice($parent2, 0, $point), array_slice($parent1, $point));
        return [$child1, $child2];
    }
    
    private function mutate(&$dayChromosome) {
        $genesPerDay = count($dayChromosome);
        for ($i = 0; $i < $genesPerDay; $i++) {
            if (mt_rand() / mt_getrandmax() < $this->mutationRate) {
                $info = $this->getGeneInfo($i);
                $isDrink = $info['isDrink'];
                if ($isDrink) {
                    $allowed = $this->getWeightedRecipes(true);
                    $dayChromosome[$i] = (mt_rand(0,1) == 0) ? 0 : (empty($allowed) ? 0 : $allowed[array_rand($allowed)]['recipe_id']);
                } else {
                    $allowed = $this->getWeightedRecipes(false);
                    $dayChromosome[$i] = empty($allowed) ? 0 : $allowed[array_rand($allowed)]['recipe_id'];
                }
            }
        }
    }
    
    public function optimize() {
        $population = [];
        for ($i = 0; $i < $this->populationSize; $i++) {
            $population[] = $this->randomWeekChromosome();
        }
        $bestFitness = -1;
        $bestIndividual = null;
        
        for ($gen = 0; $gen < $this->generations; $gen++) {
            $fitnesses = array_map([$this, 'calculateFitness'], $population);
            $currentBestIdx = array_keys($fitnesses, max($fitnesses))[0];
            if ($fitnesses[$currentBestIdx] > $bestFitness) {
                $bestFitness = $fitnesses[$currentBestIdx];
                $bestIndividual = $population[$currentBestIdx];
            }
            
            arsort($fitnesses);
            $eliteIndices = array_slice(array_keys($fitnesses), 0, $this->eliteCount);
            $newPopulation = [];
            foreach ($eliteIndices as $idx) $newPopulation[] = $population[$idx];
            
            $selected = $this->tournamentSelection($population, $fitnesses);
            while (count($newPopulation) < $this->populationSize) {
                $p1 = $selected[array_rand($selected)];
                $p2 = $selected[array_rand($selected)];
                $child = [];
                for ($day = 0; $day < $this->daysCount; $day++) {
                    list($c1, $c2) = $this->crossover($p1[$day], $p2[$day]);
                    $this->mutate($c1);
                    $child[] = $c1;
                }
                $newPopulation[] = $child;
            }
            $population = $newPopulation;
        }
        return $bestIndividual;
    }
    
    public function saveMenu($bestWeek) {
        $stmt = $this->pdo->prepare("
            INSERT INTO menus (user_id, period_start, period_end, status)
            VALUES (?, ?, ?, 'draft')
        ");
        $stmt->execute([$this->userId, $this->periodStart, $this->periodEnd]);
        $menuId = $this->pdo->lastInsertId();
        
        for ($day = 0; $day < $this->daysCount; $day++) {
            $dayChrom = $bestWeek[$day];
            $geneIdx = 0;
            foreach ($this->mealTypesOrdered as $mealType) {
                for ($d = 0; $d < $this->dishesPerMealByType[$mealType]; $d++) {
                    $rid = $dayChrom[$geneIdx++];
                    if ($rid == 0) continue;
                    $stmt = $this->pdo->prepare("
                        INSERT INTO menu_recipes (menu_id, recipe_id, meal_type, day_number, serving_size)
                        VALUES (?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([$menuId, $rid, $mealType, $day+1]);
                }
            }
            foreach ($this->mealTypesOrdered as $mealType) {
                for ($d = 0; $d < $this->drinksPerMealByType[$mealType]; $d++) {
                    $rid = $dayChrom[$geneIdx++];
                    if ($rid == 0) continue;
                    $stmt = $this->pdo->prepare("
                        INSERT INTO menu_recipes (menu_id, recipe_id, meal_type, day_number, serving_size)
                        VALUES (?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([$menuId, $rid, $mealType, $day+1]);
                }
            }
        }
        return $menuId;
    }
}

// Получение текущего меню
if ($method === 'GET' && !$id) {
    $start = $_GET['start_date'] ?? date('Y-m-d', strtotime('monday this week'));
    $end   = $_GET['end_date']   ?? date('Y-m-d', strtotime('sunday this week'));
    $stmt = $pdo->prepare("
        SELECT mr.menu_recipe_id, mr.meal_type, mr.day_number, mr.serving_size,
               r.recipe_id, r.name, r.calories_per_serving, r.image_url,
               r.protein_per_serving, r.fat_per_serving, r.carbs_per_serving,
               r.is_drink
        FROM menu_recipes mr
        JOIN menus m ON mr.menu_id = m.menu_id
        JOIN recipes r ON mr.recipe_id = r.recipe_id
        WHERE m.user_id = ? AND m.status = 'draft'
          AND m.period_start <= ? AND m.period_end >= ?
        ORDER BY mr.day_number, FIELD(mr.meal_type, 'breakfast','lunch','dinner','snack')
    ");
    $stmt->execute([$userId, $end, $start]);
    $items = $stmt->fetchAll();
    $menu = [];
    foreach ($items as $item) {
        $menu[(int)$item['day_number']][] = $item;
    }
    json_response($menu);
}

// Случайная генерация 
if ($method === 'POST' && $id === 'generate') {
    $start = date('Y-m-d', strtotime('monday this week'));
    $end   = date('Y-m-d', strtotime('sunday this week'));
    $stmt = $pdo->prepare("SELECT menu_id FROM menus WHERE user_id = ? AND period_start = ? AND period_end = ? AND status = 'draft'");
    $stmt->execute([$userId, $start, $end]);
    $menu = $stmt->fetch();
    if ($menu) {
        $menuId = $menu['menu_id'];
        $pdo->prepare("DELETE FROM menu_recipes WHERE menu_id = ?")->execute([$menuId]);
    } else {
        $pdo->prepare("INSERT INTO menus (user_id, period_start, period_end, status) VALUES (?, ?, ?, 'draft')")->execute([$userId, $start, $end]);
        $menuId = $pdo->lastInsertId();
    }
    $mealTypes = ['breakfast','lunch','dinner','snack'];
    for ($day=1; $day<=7; $day++) {
        foreach ($mealTypes as $mealType) {
            $stmt = $pdo->query("SELECT recipe_id FROM recipes WHERE is_drink = 0 ORDER BY RAND() LIMIT 1");
            $recipe = $stmt->fetch();
            if ($recipe) {
                $pdo->prepare("INSERT INTO menu_recipes (menu_id, recipe_id, meal_type, day_number, serving_size) VALUES (?, ?, ?, ?, 1)")->execute([$menuId, $recipe['recipe_id'], $mealType, $day]);
            }
        }
    }
    json_response(['message' => 'Меню сгенерировано случайным образом', 'menu_id' => $menuId]);
}

// Оптимизация генетическим алгоритмом
if ($method === 'POST' && $id === 'optimize') {
    $data = json_decode(file_get_contents('php://input'), true);
    $period_start = $data['period_start'] ?? date('Y-m-d', strtotime('monday this week'));
    $period_end   = $data['period_end']   ?? date('Y-m-d', strtotime('sunday this week'));
    $meals_per_day = (int)($data['meals_per_day'] ?? 3);
    
    $params = [
        'period_start' => $period_start,
        'period_end'   => $period_end,
        'meals_per_day' => $meals_per_day,
    ];
    
    $mealTypesAll = ['breakfast', 'lunch', 'dinner', 'snack'];
    $hasSeparate = false;
    foreach ($mealTypesAll as $mt) {
        if (isset($data["dishes_{$mt}"]) || isset($data["drinks_{$mt}"])) {
            $hasSeparate = true;
            break;
        }
    }
    
    if ($hasSeparate) {
        foreach ($mealTypesAll as $mt) {
            $params["dishes_{$mt}"] = (int)($data["dishes_{$mt}"] ?? 1);
            $params["drinks_{$mt}"] = (int)($data["drinks_{$mt}"] ?? 1);
        }
    } else {
        $params['dishes_per_meal'] = (int)($data['dishes_per_meal'] ?? 1);
        $params['drinks_per_meal'] = (int)($data['drinks_per_meal'] ?? 1);
    }
    
    $pdo->prepare("DELETE FROM menus WHERE user_id = ? AND period_start = ? AND period_end = ? AND status = 'draft'")
        ->execute([$userId, $period_start, $period_end]);
    
    try {
        $optimizer = new GeneticMenuOptimizer($pdo, $userId, $params);
        $bestMenu = $optimizer->optimize();
        $menuId = $optimizer->saveMenu($bestMenu);
        json_response(['message' => 'Меню успешно оптимизировано', 'menu_id' => $menuId]);
    } catch (Exception $e) {
        json_error($e->getMessage(), 400);
    }
}

// Назначение рецепта вручную
if ($method === 'POST' && $id === 'assign') {
    $data = json_decode(file_get_contents('php://input'), true);
    validate_required(['recipe_id', 'meal_type', 'day_number'], $data);
    
    $stmt = $pdo->prepare("SELECT is_drink, calories_per_serving FROM recipes WHERE recipe_id = ?");
    $stmt->execute([$data['recipe_id']]);
    $recipe = $stmt->fetch();
    if (!$recipe) json_error('Рецепт не найден', 404);
    $isDrink = (bool)$recipe['is_drink'];
    $recipeCalories = (int)$recipe['calories_per_serving'];
    
    $stmt = $pdo->prepare("SELECT meals_per_day, dishes_per_meal, drinks_per_meal FROM user_preferences_core WHERE user_id = ?");
    $stmt->execute([$userId]);
    $prefs = $stmt->fetch();
    $mealsPerDay = $prefs['meals_per_day'] ?? 3;
    $dishesPerMeal = $prefs['dishes_per_meal'] ?? 1;
    $drinksPerMeal = $prefs['drinks_per_meal'] ?? 1;
    
    $allowedMealTypes = array_slice(['breakfast','lunch','dinner','snack'], 0, $mealsPerDay);
    if (!in_array($data['meal_type'], $allowedMealTypes)) {
        json_error("Приём пищи '{$data['meal_type']}' не разрешён", 422);
    }
    
    $periodStart = date('Y-m-d', strtotime('monday this week'));
    $periodEnd   = date('Y-m-d', strtotime('sunday this week'));
    $stmt = $pdo->prepare("SELECT menu_id FROM menus WHERE user_id = ? AND period_start = ? AND period_end = ? AND status = 'draft'");
    $stmt->execute([$userId, $periodStart, $periodEnd]);
    $menu = $stmt->fetch();
    if (!$menu) {
        $pdo->prepare("INSERT INTO menus (user_id, period_start, period_end, status) VALUES (?, ?, ?, 'draft')")->execute([$userId, $periodStart, $periodEnd]);
        $menuId = $pdo->lastInsertId();
    } else {
        $menuId = $menu['menu_id'];
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM menu_recipes WHERE menu_id = ? AND recipe_id = ?");
    $stmt->execute([$menuId, $data['recipe_id']]);
    $count = $stmt->fetchColumn();
    if ($count >= 3) json_error("Рецепт уже используется {$count} раз", 422);
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM menu_recipes mr
        JOIN recipes r ON mr.recipe_id = r.recipe_id
        WHERE mr.menu_id = ? AND mr.day_number = ? AND mr.meal_type = ? AND r.is_drink = ?
    ");
    $stmt->execute([$menuId, $data['day_number'], $data['meal_type'], $isDrink ? 1 : 0]);
    $currentCount = $stmt->fetchColumn();
    $limit = $isDrink ? $drinksPerMeal : $dishesPerMeal;
    if ($currentCount >= $limit) json_error("Лимит {$limit} для " . ($isDrink ? "напитков" : "блюд"), 422);
    
    $stmt = $pdo->prepare("
        SELECT SUM(r.calories_per_serving * mr.serving_size) as daily_cal
        FROM menu_recipes mr
        JOIN recipes r ON mr.recipe_id = r.recipe_id
        WHERE mr.menu_id = ? AND mr.day_number = ?
    ");
    $stmt->execute([$menuId, $data['day_number']]);
    $currentDayCal = (int)$stmt->fetchColumn();
    if ($currentDayCal + $recipeCalories > 4000) json_error("Превышение дневной нормы калорий", 422);
    
    $pdo->prepare("
        INSERT INTO menu_recipes (menu_id, recipe_id, meal_type, day_number, serving_size)
        VALUES (?, ?, ?, ?, ?)
    ")->execute([$menuId, $data['recipe_id'], $data['meal_type'], $data['day_number'], $data['serving_size'] ?? 1]);
    
    json_response(['message' => 'Рецепт добавлен в меню']);
}

// Удаление рецепта из меню
if ($method === 'DELETE' && is_numeric($id)) {
    $stmt = $pdo->prepare("
        SELECT mr.menu_recipe_id FROM menu_recipes mr
        JOIN menus m ON mr.menu_id = m.menu_id
        WHERE mr.menu_recipe_id = ? AND m.user_id = ? AND m.status = 'draft'
    ");
    $stmt->execute([$id, $userId]);
    if (!$stmt->fetch()) json_error('Запись не найдена', 404);
    $pdo->prepare("DELETE FROM menu_recipes WHERE menu_recipe_id = ?")->execute([$id]);
    json_response(['message' => 'Рецепт удалён из меню']);
}

// Альтернативы для замены
if ($method === 'GET' && $id === 'alternatives') {
    $menuRecipeId = $_GET['meal_id'] ?? null;
    $calMax = (int)($_GET['cal_max'] ?? 1000);
    $excludeId = 0;
    $isDrink = null;
    
    if ($menuRecipeId) {
        $stmt = $pdo->prepare("
            SELECT r.recipe_id, r.is_drink, r.calories_per_serving
            FROM menu_recipes mr
            JOIN recipes r ON mr.recipe_id = r.recipe_id
            WHERE mr.menu_recipe_id = ?
        ");
        $stmt->execute([$menuRecipeId]);
        $current = $stmt->fetch();
        if ($current) {
            $excludeId = $current['recipe_id'];
            $isDrink = (int)$current['is_drink'];
            if (!isset($_GET['cal_max'])) {
                $calMax = max(100, (int)($current['calories_per_serving'] * 1.2));
            }
        }
    }
    
    if ($isDrink === null) json_error('Не удалось определить тип блюда', 400);
    
    $stmt = $pdo->prepare("SELECT max_difficulty FROM user_preferences_core WHERE user_id = ?");
    $stmt->execute([$userId]);
    $maxDiff = $stmt->fetchColumn() ?: 2;
    
    $stmt = $pdo->prepare("SELECT allergen_type FROM user_allergens WHERE user_id = ?");
    $stmt->execute([$userId]);
    $allergens = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $stmt = $pdo->prepare("SELECT excluded_product_name FROM user_excluded_products WHERE user_id = ?");
    $stmt->execute([$userId]);
    $excludedProducts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $sql = "
        SELECT r.recipe_id, r.name, r.calories_per_serving, r.cooking_time, r.difficulty, r.image_url
        FROM recipes r
        WHERE r.recipe_id != ?
          AND r.is_drink = ?
          AND r.calories_per_serving <= ?
          AND r.difficulty <= ?
    ";
    $params = [$excludeId, $isDrink, $calMax, $maxDiff];
    
    if (!empty($allergens)) {
        $placeholders = implode(',', array_fill(0, count($allergens), '?'));
        $sql .= " AND r.recipe_id NOT IN (
            SELECT ri.recipe_id FROM recipe_ingredients ri
            JOIN ingredients i ON ri.ingredient_id = i.ingredient_id
            WHERE i.name IN ($placeholders)
        )";
        $params = array_merge($params, $allergens);
    }
    
    if (!empty($excludedProducts)) {
        $placeholders = implode(',', array_fill(0, count($excludedProducts), '?'));
        $sql .= " AND r.recipe_id NOT IN (
            SELECT ri.recipe_id FROM recipe_ingredients ri
            JOIN ingredients i ON ri.ingredient_id = i.ingredient_id
            WHERE i.name IN ($placeholders)
        )";
        $params = array_merge($params, $excludedProducts);
    }
    
    $sql .= " ORDER BY RAND() LIMIT 6";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    json_response($stmt->fetchAll());
}

// Добавление избранного рецепта
if ($method === 'POST' && $id === 'add-favorite') {
    $data = json_decode(file_get_contents('php://input'), true);
    validate_required(['recipe_id'], $data);
    
    $stmt = $pdo->prepare("SELECT 1 FROM user_favorites WHERE user_id = ? AND recipe_id = ?");
    $stmt->execute([$userId, $data['recipe_id']]);
    if (!$stmt->fetch()) json_error('Рецепт не найден в избранном', 404);
    
    $stmt = $pdo->prepare("SELECT is_drink FROM recipes WHERE recipe_id = ?");
    $stmt->execute([$data['recipe_id']]);
    $recipe = $stmt->fetch();
    if (!$recipe) json_error('Рецепт не найден', 404);
    $isDrink = (bool)$recipe['is_drink'];
    
    $stmt = $pdo->prepare("SELECT meals_per_day, dishes_per_meal, drinks_per_meal FROM user_preferences_core WHERE user_id = ?");
    $stmt->execute([$userId]);
    $prefs = $stmt->fetch();
    $mealsPerDay = $prefs['meals_per_day'] ?? 3;
    $dishesPerMeal = $prefs['dishes_per_meal'] ?? 1;
    $drinksPerMeal = $prefs['drinks_per_meal'] ?? 1;
    
    $mealTypes = ['breakfast', 'lunch', 'dinner', 'snack'];
    $usedMealTypes = array_slice($mealTypes, 0, $mealsPerDay);
    
    $periodStart = date('Y-m-d', strtotime('monday this week'));
    $periodEnd   = date('Y-m-d', strtotime('sunday this week'));
    
    $stmt = $pdo->prepare("SELECT menu_id FROM menus WHERE user_id = ? AND period_start = ? AND period_end = ? AND status = 'draft'");
    $stmt->execute([$userId, $periodStart, $periodEnd]);
    $menu = $stmt->fetch();
    if (!$menu) {
        $pdo->prepare("INSERT INTO menus (user_id, period_start, period_end, status) VALUES (?, ?, ?, 'draft')")->execute([$userId, $periodStart, $periodEnd]);
        $menuId = $pdo->lastInsertId();
    } else {
        $menuId = $menu['menu_id'];
    }
    
    $day = 1;
    $inserted = false;
    foreach ($usedMealTypes as $mealType) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM menu_recipes mr
            JOIN recipes r ON mr.recipe_id = r.recipe_id
            WHERE mr.menu_id = ? AND mr.day_number = ? AND mr.meal_type = ? AND r.is_drink = ?
        ");
        $stmt->execute([$menuId, $day, $mealType, $isDrink ? 1 : 0]);
        $current = $stmt->fetchColumn();
        $limit = $isDrink ? $drinksPerMeal : $dishesPerMeal;
        if ($current < $limit) {
            $pdo->prepare("INSERT INTO menu_recipes (menu_id, recipe_id, meal_type, day_number, serving_size) VALUES (?, ?, ?, ?, 1)")->execute([$menuId, $data['recipe_id'], $mealType, $day]);
            $inserted = true;
            break;
        }
    }
    
    if (!$inserted) json_error("Нет свободного места", 422);
    json_response(['message' => 'Избранный рецепт добавлен']);
}

// Применение массовых корректировок
if ($method === 'POST' && $id === 'apply-corrections') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['updates']) || !is_array($data['updates'])) json_error('Неверный формат', 422);
    $start = date('Y-m-d', strtotime('monday this week'));
    $end   = date('Y-m-d', strtotime('sunday this week'));
    $stmt = $pdo->prepare("SELECT menu_id FROM menus WHERE user_id = ? AND period_start = ? AND period_end = ? AND status = 'draft'");
    $stmt->execute([$userId, $start, $end]);
    $menu = $stmt->fetch();
    if (!$menu) json_error('Нет активного меню', 400);
    $menuId = $menu['menu_id'];
    foreach ($data['updates'] as $upd) {
        if (!isset($upd['day_number'], $upd['meal_type'], $upd['recipe_id'])) continue;
        $pdo->prepare("DELETE FROM menu_recipes WHERE menu_id = ? AND day_number = ? AND meal_type = ?")->execute([$menuId, $upd['day_number'], $upd['meal_type']]);
        if ($upd['recipe_id'] != 0) {
            $pdo->prepare("INSERT INTO menu_recipes (menu_id, recipe_id, meal_type, day_number, serving_size) VALUES (?, ?, ?, ?, ?)")->execute([$menuId, $upd['recipe_id'], $upd['meal_type'], $upd['day_number'], $upd['serving_size'] ?? 1]);
        }
    }
    json_response(['message' => 'Корректировки применены']);
}

// Статистика по дням
if ($method === 'GET' && $id === 'stats') {
    $start = $_GET['start_date'] ?? date('Y-m-d', strtotime('monday this week'));
    $end   = $_GET['end_date']   ?? date('Y-m-d', strtotime('sunday this week'));
    $stmt = $pdo->prepare("
        SELECT mr.day_number, SUM(r.calories_per_serving * mr.serving_size) as daily_calories,
               SUM(r.protein_per_serving * mr.serving_size) as daily_protein,
               SUM(r.fat_per_serving * mr.serving_size) as daily_fat,
               SUM(r.carbs_per_serving * mr.serving_size) as daily_carbs
        FROM menu_recipes mr
        JOIN menus m ON mr.menu_id = m.menu_id
        JOIN recipes r ON mr.recipe_id = r.recipe_id
        WHERE m.user_id = ? AND m.status = 'draft'
          AND m.period_start <= ? AND m.period_end >= ?
        GROUP BY mr.day_number
        ORDER BY mr.day_number
    ");
    $stmt->execute([$userId, $end, $start]);
    json_response($stmt->fetchAll());
}

// Копирование меню
if ($method === 'POST' && $id === 'copy') {
    $data = json_decode(file_get_contents('php://input'), true);
    $newStart = $data['new_start'] ?? null;
    $newEnd   = $data['new_end']   ?? null;
    if (!$newStart || !$newEnd) json_error('Укажите новый период', 422);
    $oldStart = date('Y-m-d', strtotime('monday this week'));
    $oldEnd   = date('Y-m-d', strtotime('sunday this week'));
    $stmt = $pdo->prepare("SELECT menu_id FROM menus WHERE user_id = ? AND period_start = ? AND period_end = ? AND status = 'draft'");
    $stmt->execute([$userId, $oldStart, $oldEnd]);
    $oldMenu = $stmt->fetch();
    if (!$oldMenu) json_error('Нет исходного меню', 404);
    $oldMenuId = $oldMenu['menu_id'];
    $pdo->prepare("INSERT INTO menus (user_id, period_start, period_end, status) VALUES (?, ?, ?, 'draft')")->execute([$userId, $newStart, $newEnd]);
    $newMenuId = $pdo->lastInsertId();
    $stmt = $pdo->prepare("SELECT recipe_id, meal_type, day_number, serving_size FROM menu_recipes WHERE menu_id = ?");
    $stmt->execute([$oldMenuId]);
    $oldRecipes = $stmt->fetchAll();
    $daysOffset = (strtotime($newStart) - strtotime($oldStart)) / 86400;
    foreach ($oldRecipes as $rec) {
        $newDay = $rec['day_number'] + $daysOffset;
        $newDaysCount = (strtotime($newEnd)-strtotime($newStart))/86400+1;
        if ($newDay >= 1 && $newDay <= $newDaysCount) {
            $pdo->prepare("INSERT INTO menu_recipes (menu_id, recipe_id, meal_type, day_number, serving_size) VALUES (?, ?, ?, ?, ?)")
                ->execute([$newMenuId, $rec['recipe_id'], $rec['meal_type'], $newDay, $rec['serving_size']]);
        }
    }
    json_response(['message' => 'Меню скопировано', 'menu_id' => $newMenuId]);
}

json_error('Метод не поддерживается', 405);