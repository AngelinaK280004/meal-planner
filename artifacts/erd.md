# ERD — модель данных

> Всего — 19 таблиц, нормализация до 4NF.

## Диаграмма

```mermaid
erDiagram
    USERS ||--o{ USER_DIETARY_MASKS : has
    USERS ||--o{ USER_ALLERGIES : has
    USERS ||--o{ USER_EXCLUDED_INGREDIENTS : excludes
    USERS ||--o{ FAVORITES : favorites
    USERS ||--o{ RECIPE_RATINGS : rates
    USERS ||--o{ USER_STOCK : owns
    USERS ||--o{ MENUS : creates
    USERS ||--o{ SHOPPING_LISTS : owns

    DIETARY_MASKS ||--o{ USER_DIETARY_MASKS : "assigned to"

    CUISINES ||--o{ RECIPES : classifies
    RECIPE_CATEGORIES ||--o{ RECIPES : classifies
    INGREDIENT_CATEGORIES ||--o{ INGREDIENTS : classifies

    RECIPES ||--o{ RECIPE_INGREDIENTS : contains
    INGREDIENTS ||--o{ RECIPE_INGREDIENTS : "used in"

    RECIPES ||--o{ FAVORITES : "in favorites"
    RECIPES ||--o{ RECIPE_RATINGS : "rated by"

    INGREDIENTS ||--o{ USER_ALLERGIES : allergen
    INGREDIENTS ||--o{ USER_EXCLUDED_INGREDIENTS : excluded
    INGREDIENTS ||--o{ USER_STOCK : stocked

    MENUS ||--o{ MENU_ITEMS : contains
    RECIPES ||--o{ MENU_ITEMS : planned

    MENUS ||--o{ SHOPPING_LISTS : generates
    SHOPPING_LISTS ||--o{ SHOPPING_LIST_ITEMS : contains
    INGREDIENTS ||--o{ SHOPPING_LIST_ITEMS : listed

    USERS {
        int id PK
        string email UK
        string password_hash
        enum role "user|admin"
        string first_name
        enum gender "male|female"
        date birth_date
        decimal height_cm
        decimal weight_kg
        enum activity_level "1.2|1.375|1.55|1.725|1.9"
        enum goal "lose|maintain|gain"
        int dietary_mask "битовая маска"
        datetime created_at
        datetime updated_at
    }

    DIETARY_MASKS {
        int id PK
        string code UK
        string name
        int bit_position "0..9"
    }

    USER_DIETARY_MASKS {
        int user_id FK
        int mask_id FK
    }

    INGREDIENTS {
        int id PK
        string name
        int category_id FK
        decimal calories_per_100g
        decimal protein_per_100g
        decimal fat_per_100g
        decimal carbs_per_100g
        string unit "g|ml|pcs"
    }

    INGREDIENT_CATEGORIES {
        int id PK
        string name
    }

    CUISINES {
        int id PK
        string name
    }

    RECIPE_CATEGORIES {
        int id PK
        string name
    }

    RECIPES {
        int id PK
        string title
        string description
        string image_url
        int cuisine_id FK
        int category_id FK
        int cooking_time_min
        enum difficulty "easy|medium|hard"
        int servings
        int dietary_mask "битовая маска"
        decimal calories
        decimal protein
        decimal fat
        decimal carbs
        text instructions
    }

    RECIPE_INGREDIENTS {
        int recipe_id FK
        int ingredient_id FK
        decimal quantity
        string unit
    }

    USER_ALLERGIES {
        int user_id FK
        int ingredient_id FK
    }

    USER_EXCLUDED_INGREDIENTS {
        int user_id FK
        int ingredient_id FK
    }

    FAVORITES {
        int user_id FK
        int recipe_id FK
        datetime created_at
    }

    RECIPE_RATINGS {
        int user_id FK
        int recipe_id FK
        int rating "1..5"
        datetime created_at
    }

    USER_STOCK {
        int id PK
        int user_id FK
        int ingredient_id FK
        decimal quantity "CHECK >= 0"
        string unit
        datetime updated_at
    }

    MENUS {
        int id PK
        int user_id FK
        date start_date
        date end_date
        int meals_per_day
        int dishes_per_meal
        int drinks_per_meal
        datetime created_at
    }

    MENU_ITEMS {
        int id PK
        int menu_id FK
        int recipe_id FK
        date day_date
        enum meal_type "breakfast|lunch|dinner|snack"
        int position
        bool is_drink
    }

    SHOPPING_LISTS {
        int id PK
        int menu_id FK
        int user_id FK
        datetime created_at
    }

    SHOPPING_LIST_ITEMS {
        int id PK
        int shopping_list_id FK
        int ingredient_id FK
        decimal required_qty
        string unit
        bool purchased
    }
```

## Описание сущностей

| Таблица | Назначение | Ключевые ограничения |
|---|---|---|
| `users` | Учётные записи, антропометрия, цель, активность, битовая маска ограничений | `email UNIQUE`, `password_hash` — bcrypt |
| `dietary_masks` | Справочник 10 диетических масок | `code UNIQUE`, `bit_position ∈ [0,9]` |
| `user_dietary_masks` | Связь пользователя и активных масок (если не хранится целиком в `users.dietary_mask`) | `[СВЕРИТЬ: вариант хранения — в users или отдельной таблицей]` |
| `ingredients` | Ингредиенты с КБЖУ на 100 г | `calories_per_100g ≥ 0` |
| `ingredient_categories` | Категории ингредиентов (для группировки покупок) | `name UNIQUE` |
| `cuisines` | Кухни мира (для контентной фильтрации) | `name UNIQUE` |
| `recipe_categories` | Категории рецептов (супы, салаты, …) | `name UNIQUE` |
| `recipes` | Рецепты с КБЖУ, инструкцией, ограничениями | `dietary_mask` — битовая маска рецепта |
| `recipe_ingredients` | Состав рецепта | составной PK `(recipe_id, ingredient_id)` |
| `user_allergies` | Аллергены пользователя | составной PK |
| `user_excluded_ingredients` | Исключённые продукты | составной PK |
| `favorites` | Избранные рецепты | составной PK, `created_at` |
| `recipe_ratings` | Оценки рецептов 1–5 (источник для коллаборативной фильтрации) | `rating BETWEEN 1 AND 5` |
| `user_stock` | Остатки продуктов на кухне | `quantity CHECK (quantity >= 0)` |
| `menus` | Сгенерированное меню на период | `start_date ≤ end_date`, период ≤ 31 день `[СВЕРИТЬ]` |
| `menu_items` | Позиции меню | `meal_type`, `position` |
| `shopping_lists` | Список покупок на меню | связь 1:1 с `menus` `[СВЕРИТЬ]` |
| `shopping_list_items` | Позиции списка покупок | `purchased` — отметка покупки |

## Обоснование нормализации до 4NF

- Устранены многозначные зависимости: диетические маски, аллергены и исключённые продукты вынесены в отдельные таблицы связи `user_*`.
- Атрибуты рецепта (название, кухня, категория) отделены от его состава (`recipe_ingredients`), что исключает дублирование КБЖУ ингредиентов при каждом рецепте.
- Оценки пользователей (`recipe_ratings`) не дублируются в `recipes` — это позволяет пересчитывать коллаборативную часть рекомендаций без денормализации.

## Компромиссы

- Большое количество JOIN при выборке меню и списка покупок — цена нормализации. Для целевой нагрузки пет-проекта оправдано; при росте нагрузки можно ввести кэш (Redis) или материализованное представление.
- Битовая маска в `users.dietary_mask` и `recipes.dietary_mask` — осознанная денормализация ради скорости фильтрации, при этом справочник `dietary_masks` сохраняет расширяемость.
