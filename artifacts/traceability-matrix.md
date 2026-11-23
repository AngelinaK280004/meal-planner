# Матрица трассировки

Связь «требование → проектное решение → артефакт → критерий приёмки».

| Требование | Решение / компонент | Артефакт | Критерий приёмки |
|---|---|---|---|
| `FR-01` Аутентификация и роли | SPA → API `/api/auth.php`, bcrypt | `c4-container.md`, `requirements.md` | Gherkin FR-01 |
| `FR-02` Расчёт КБЖУ | `api/user.php`, формула Миффлина-Сан-Жеора | `requirements.md` | Gherkin FR-02 |
| `FR-03` Диетические ограничения | битовая маска, `dietary_masks`, `user_dietary_masks` | `erd.md`, `requirements.md` | Gherkin FR-03 |
| `FR-04` Поиск и фильтрация рецептов | `api/recipes.php`, фильтры в SQL | `requirements.md` | Gherkin FR-04 |
| `FR-05` Остатки продуктов | `user_stock`, `CHECK quantity >= 0` | `erd.md` | Gherkin FR-05 |
| `FR-06` Генерация меню | ГА в `api/menu.php`, `menu_items` | `requirements.md`, `erd.md` | Gherkin FR-06 |
| `FR-07` Замена блюда | `api/menu.php`, альтернативы с учётом ограничений | `requirements.md` | Gherkin FR-07 |
| `FR-08` Список покупок | `api/shopping.php`, `required = max(0, needed − stock)` | `requirements.md`, `erd.md` | Gherkin FR-08 |
| `FR-09` Дашборд | `api/dashboard.php`, SPA | `requirements.md` | Gherkin FR-09 |
| `FR-10` Рекомендации | гибрид `0.6*content + 0.4*(collab/5)`, `recipe_ratings` | `requirements.md`, `erd.md` | Gherkin FR-10 |
| `NFR-PERF-01` Генерация ≤ 2 с | параметры ГА, оптимизация запросов | `requirements.md` | метрика «0.85 с» |
| `NFR-PERF-02` 100 пользователей | Apache + PHP-FPM `[СВЕРИТЬ]`, кэш Redis | `c4-container.md` | JMeter ≤ 1.05 с |
| `NFR-SEC-01` PDO | PDO подготовленные выражения в `api/*.php` | `c4-container.md` | code review |
| `NFR-SEC-02` bcrypt | `password_hash()` | `c4-container.md` | проверка БД |
| `NFR-SEC-03` RBAC | проверка роли в `api/admin.php` | `c4-container.md` | Gherkin FR-01 |
| `NFR-SEC-04` XSS | `escapeHtml` | `c4-container.md` | XSS-тест |
| `NFR-DATA-01` CHECK остатков | `db/schema.sql` | `erd.md` | миграция |

