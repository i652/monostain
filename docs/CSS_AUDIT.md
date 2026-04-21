# Аудит `style.css`: поиск неиспользуемых стилей

Полная статическая проверка «лишних» правил **не гарантируется**: классы могут добавляться из JS (`innerHTML`, `classList`), из ответов API или редких шаблонов.

## Практичный процесс

1. **Список селекторов из CSS** (упрощённо — классы):

   ```bash
   rg -o '\.[a-zA-Z][a-zA-Z0-9_-]*' public/assets/style.css | sort -u > /tmp/css-classes.txt
   ```

   Без `rg` (только `grep`):

   ```bash
   grep -oE '\.[a-zA-Z][a-zA-Z0-9_-]*' public/assets/style.css | sort -u > /tmp/css-classes.txt
   ```

2. **Поиск по проекту** (PHP, HTML в шаблонах, JS):

   ```bash
   while read -r c; do
     name="${c#.}"
     rg -q "$name" templates public/assets/*.js src || echo "MAYBE_UNUSED $name"
   done < /tmp/css-classes.txt
   ```

   Вариант на `grep` (рекурсия по `templates`, `public/assets`, `src`):

   ```bash
   while IFS= read -r c; do
     name="${c#.}"
     if ! grep -rq --include='*.php' --include='*.js' "$name" templates public/assets src; then
       echo "MAYBE_UNUSED $name"
     fi
   done < /tmp/css-classes.txt
   ```

Файл [`css-maybe-unused.txt`](css-maybe-unused.txt) — журнал последнего аудита и зачистки неиспользуемых правил (перегенерация: шаги выше).

   Ложные срабатывания: короткие имена (`open`, `show`), модификаторы БЭM, классы только в пользовательском HTML постов.

3. **Покрытие из шаблонов**: 

   ```bash
   rg -o 'class="[^"]*"' templates | tr ' ' '\n' | rg -o '[a-zA-Z0-9_-]+' | sort -u
   ```

4. **Динамика**: искать в `public/assets/*.js` строки с именами классов (`post-card`, `panel-list`, `toast`).

## Наблюдения по текущему репозиторию

- Файл стилей большой, часть правил историческая (комментарии «legacy» уже вычищались).
- Дублирование селекторов (например `.post-full` в нескольких местах) лучше сворачивать при следующем рефакторинге, а не удалять наугад.
- Селекторы `#panel-toggle` / `auth-toggle` могут относиться к разметке, которую редко открывают — проверить в шаблонах перед удалением.

## Рекомендация

Раз в релиз прогонять шаги 1–2 по **новым** классам после крупных UI-изменений; удалять блок только при подтверждении отсутствия в `templates/`, `public/assets/*.js` и типовом контенте постов.
