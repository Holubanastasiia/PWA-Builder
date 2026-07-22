# ТЗ: WordPress PWA Builder

## 1. Мета

Потрібно створити внутрішній сервіс на WordPress для генерації та керування PWA-додатками під маркетингові кампанії.

Сервіс має замінити сторонні PWA-сервіси, які зараз використовуються для наливу трафіку, і дати команді контроль над:

- доменами;
- PWA-додатками;
- шаблонами;
- редіректами;
- параметрами трафіку;
- інтеграцією з власною аналітикою;
- майбутніми пушами;
- майбутньою клоакою.

Основний фокус цього ТЗ: **ядро PWA Builder**.

Аналітика, пуші та клоака мають бути окремими системами/плагінами.

## 2. Загальна Архітектура

Систему потрібно розділити на незалежні частини.

### 2.1. PWA Builder Plugin

Основний плагін для створення та рендерингу PWA.

Відповідає за:

- створення PWA-записів в адмінці;
- налаштування PWA;
- вибір ніші;
- вибір шаблону;
- вибір flow-поведінки;
- генерацію manifest;
- генерацію service worker;
- генерацію внутрішнього launch/start URL для встановленої PWA;
- рендер технічного start/redirect shell;
- рендер PWA-сторінки без хедера/футера активної теми;
- OS-specific templates: Android / iOS / fallback; (це для гугл плей та ап стор темплейтів, щоб автоматично визначати який показувати)
- передачу контексту сторінки та frontend events/hooks для аналітики; (уточнити у Макса все по івентам)
- підключення assets конкретного шаблону;
- підтримку reusable interactive components.

Не відповідає за:

- відправку аналітики у зовнішню тулзу;
- збір, нормалізацію та трансформацію traffic params;
- підстановку параметрів у offer URL;
- webview escape (`intent://`, `x-safari-https://`);
- декодування `lead_info`;
- пуш кампанії;
- клоакінг.

### 2.2. Analytics / Tracking Plugin

Окремий плагін, який уже існує і буде розширюватися.

Відповідає за:

- читання query parameters;
- нормалізацію та трансформацію query parameters;
- генерацію/зберігання `click_id`, `user_id`;
- cookies/localStorage для аналітики;
- декодування та збереження `lead_info`, якщо використовується така модель;
- збереження attribution/context для повторного відкриття встановленої PWA;
- webview escape (`intent://...package=com.android.chrome`, `x-safari-https://...`);
- поведінку CTA/install button, якщо вона залежить від attribution/redirect логіки;
- відправку visit/click/install/open/redirect events; (уточнити все у Макса)
- підстановку параметрів у offer URL;

PWA Builder має тільки давати йому контекст PWA App, DOM hooks і frontend events.

Важливо:

- аналітика є власником traffic params;
- PWA Builder не має дублювати логіку збору/трансформації параметрів;
- PWA Builder має рендерити CTA/redirect links з потрібними класами/атрибутами, щоб analytics plugin міг сам підставити параметри.
- якщо використовується encoded payload типу `lead_info`, analytics plugin є власником його декодування, валідації та збереження;
- якщо PWA відкривається з home screen без query params, analytics plugin має вміти відновити attribution з cookie/localStorage або іншого agreed storage.

### 2.3. Push / Central Push Management

Пуші не варто повністю керувати з кожного окремого WordPress-сайту.

Бажана модель:

- централізована управлялка пушами;
- інтеграція з analytics plugin / аналітичною системою;
- WordPress/PWA Builder тільки дає технічні точки підключення.

Причина:

- доменів буде багато;
- на кожному домені може бути багато PWA;
- керувати push campaigns окремо з кожного WP-сайту буде незручно;
- для маркетингу потрібна нормальна централізована панель керування.

Ймовірний провайдер:

- Firebase;
- або Web Push;
- фінально вирішити окремо.

Відповідає за:

- push subscription;
- VAPID/Firebase config;
- campaigns;
- resubscribe;
- push open/close events;
- статистику пушів.

PWA Builder має дати тільки:

- integration point у frontend;
- integration point у service worker;
- можливість передати push subscription у зовнішню/центральну push-систему;
- можливість увімкнути/вимкнути push logic для конкретної PWA, якщо буде потрібно.

### 2.4. Cloak Plugin

Клоака має бути **повністю незалежним плагіном**.

Вона не повинна бути частиною PWA Builder, бо може використовуватись:

- для PWA;
- для звичайних лендингів;
- для інших маркетингових сторінок;
- для будь-яких URL на WordPress.

Окремий плагін умовно:

```text
wp-traffic-cloak
```

Відповідає за:

- GEO rules;
- OS/device/browser rules;
- query parameter rules;
- allow/deny logic;
- white page;
- safe page;
- redirect;
- логування рішень; (подумати з Дімою куди локувати все)
- debug/test mode.

PWA Builder має тільки запитувати рішення:

```php
apply_filters('wp_pwa_builder_cloak_decision', null, $app, $request); 
```
- теж не факт що навіть це потрібно. Робимо чисто окремим плагіном за аналогією зі сплітом. 

Але сам PWA Builder не реалізує клоаку.

### 2.5. Референс BetterLinks / Що Беремо В Свою Архітектуру

Поточний сторонній сервіс використовує корисну для нас модель:

```text
landing page
-> install/redirect decision
-> technical play/start URL
-> events / push / attribution
-> redirect to offer
```

Корисні ідеї, які варто адаптувати:

- `manifest.start_url` веде не на prelanding UI, а на технічний URL для запуску встановленої PWA;
- технічний URL знаходиться в межах `scope`;
- перший вхід з рекламних параметрів зберігає attribution у storage;
- повторне відкриття PWA з home screen відновлює attribution зі storage;
- CTA/install button не завжди веде одразу на offer, а може вести на technical start/play URL;
- на technical start/play URL збираються launch/open/redirect events;
- після цього виконується redirect to offer;
- webview escape та encoded traffic payload мають бути окремою логікою analytics plugin.

Що не копіюємо напряму:

- не кладемо Firebase config прямо в PWA Builder template JS;
- не змішуємо push, redirect, attribution і template UI в одному великому frontend-файлі;
- не робимо PWA Builder власником `lead_info`, `offer_url`, `lead_uuid`;
- не робимо PWA Builder другим analytics plugin.

Наша адаптована модель:

```text
/apps/{slug}/
-> PWA landing/template
-> analytics plugin reads params / lead_info
-> analytics plugin stores attribution
-> user CTA / install flow
-> /apps/{slug}/start/
-> analytics plugin sends app_open / redirect events
-> redirect to offer
```

## 3. Основна Сутність: PWA App

У WordPress має бути custom post type:

```text
pwa_app
```

Приклад URL:

```text
/apps/{slug}/
```
Перепитати чи нада нам категорія /apps/ чи можна одразу слаг і подумати як буде легше. 
Один `PWA App` = один PWA-додаток / одна маркетингова сутність.

`PWA App` не обов'язково дорівнює одній кампанії.

Один PWA App може використовуватися для декількох кампаній, якщо це однаковий app/template/offer-flow. Якщо потрібно швидко створити схожу версію, можна використовувати Yoast Duplicate Post або аналогічний duplicate plugin, увімкнувши підтримку `pwa_app` у його налаштуваннях.

Базовий сценарій:

- менеджер створює один PWA App;
- контент заповнюється один раз;
- Android/iOS/fallback версії можуть рендеритись автоматично через template variants.

Але система не повинна забороняти OS split.

Якщо в окремому split plugin вже є можливість обрати різні воронки під iOS та Android, PWA Builder має не заважати цьому підходу. (у мене вже є спліт під різні os)

Приклад:

```text
Android -> красивий PWA landing / install flow
iOS -> прямий redirect flow
```

Тобто можливі два сценарії:

1. Один PWA App з OS-specific template variants.
2. Split plugin окремо розводить трафік на різні PWA Apps / flows / URLs залежно від OS.

Фінальну модель потрібно узгодити з лідом і поточним split plugin.

## 4. Поля PWA App

### 4.1. Базові Поля

- App name.
- Slug.
- Short name.
- WordPress publish status.
- Niche.
- Template.
- Flow behavior.
- Offer URL.
- Theme color.
- Background color.
- App icon.
- Mobile screenshot.
- Wide screenshot.

### 4.2. Slug vs Short Name

`Slug` - технічна частина URL.

Приклад:

```text
/apps/lanista-glory/
```

`Short name` - коротка назва для manifest, яка може показуватись під іконкою встановленої PWA.

Приклад:

```text
Lanista
```

### 4.3. Template-Specific Fields

Поля залежать від шаблону.

Наприклад для App Store / Google Play шаблону:

- developer name;
- rating;
- reviews count;
- installs count;
- category;
- age label;
- description;
- screenshots;
- comments/reviews;
- CTA text;
- privacy/data safety blocks.

Поля бажано зберігати через ACF JSON.

## 5. Ніші

Система має підтримувати багато ніш.

Приклади:

- `igaming`;
- `insurance`;
- інші ніші в майбутньому.

Вимоги:

- кожен PWA App має вибрану нішу;
- кожен template може вказати, для яких ніш він доступний;
- в адмінці template dropdown має фільтруватися за нішею;
- нові ніші мають додаватися через registry/config, без переписування логіки шаблонів.

## 6. Шаблони

Шаблони мають зберігатися в папках.

Приклад:

```text
templates/
  store-app/
    template.json
    android/
      template.php
      style.css
      script.js
    ios/
      template.php
      style.css
      script.js
    fallback/
      template.php
      style.css
      script.js
```

Кожен шаблон має `template.json`.

Приклад:

```json
{
  "name": "App Store / Play Market",
  "niches": ["igaming", "insurance"],
  "variants": {
    "android": {
      "template": "android/template.php",
      "styles": ["android/style.css"],
      "scripts": ["android/script.js"]
    },
    "ios": {
      "template": "ios/template.php",
      "styles": ["ios/style.css"],
      "scripts": ["ios/script.js"]
    },
    "fallback": {
      "template": "fallback/template.php",
      "styles": ["fallback/style.css"],
      "scripts": ["fallback/script.js"]
    }
  }
}
```

Новий шаблон має додаватися без ручної реєстрації в PHP:

1. Створити папку в `templates/`.
2. Додати `template.json`.
3. Додати PHP/CSS/JS файли.
4. Додати ACF JSON поля, якщо потрібні.

## 7. OS-Specific Rendering

Один шаблон може мати різний вигляд для різних OS.

Потрібні variants:

- Android;
- iOS;
- fallback/desktop/unknown.

Логіка:

1. Визначити OS.
2. Якщо є variant для цієї OS - рендерити його.
3. Якщо немає - рендерити `fallback`.
4. Якщо немає fallback - рендерити default template.

Приклад:

```text
Android -> Google Play style
iOS -> App Store style
Desktop/unknown -> fallback
```

## 7.1. OS Split / Воронки За OS

Окремо від template variants має бути можливість використовувати існуючий split plugin.

Це потрібно, якщо для різних OS потрібні не просто різні UI variants, а повністю різні воронки.

Приклад:

```text
Android traffic -> PWA App з Google Play-style landing
iOS traffic -> redirect-only flow без PWA landing
```

PWA Builder не має дублювати split plugin і не потребує окремої інтеграції з ним у MVP.

Очікувана інтеграція:

- PWA Builder має мати стабільні URL, які можна використовувати як destination у split plugin;
- split plugin сам направляє traffic на різні PWA Apps або різні flows;
- PWA Builder має коректно працювати, якщо на нього прийшов уже відфільтрований OS traffic.

Рішення для MVP:

- PWA Builder-level OS routing не робимо;
- якщо потрібне повне розділення воронок за OS, використовуємо існуючий split plugin окремо;
- template variants залишаються для випадків, коли один PWA App має різний UI під Android/iOS/fallback.

## 8. Clean Frontend Shell

PWA-сторінки не мають використовувати хедер і футер активної WP-теми.

Потрібен власний мінімальний shell:

```text
<!doctype html>
<html>
  <head>
    wp_head()
  </head>
  <body>
    wp_body_open()
    selected PWA template
    wp_footer()
  </body>
</html>
```

Це потрібно, щоб тема блогу не додавала:

- header;
- footer;
- menu;
- sidebar;
- зайві стилі;
- зайвий layout.

## 9. Manifest

Для кожного PWA App має генеруватись manifest.

Приклад URL:

```text
/apps/{slug}/manifest.webmanifest
```

Manifest має включати:

- `id`;
- `name`;
- `short_name`;
- `description`;
- `start_url`;
- `scope`;
- `display`;
- `orientation`;
- `theme_color`;
- `background_color`;
- `icons`;
- `screenshots`.

### 9.1. Icons

Мінімально:

- 192x192;
- 512x512.

Бажано:

- окремо `any`;
- окремо `maskable`, якщо потрібно.

Не бажано використовувати один і той самий icon з `purpose: "any maskable"`, якщо padding не перевірений.

### 9.2. Screenshots

Бажано підтримувати:

- mobile screenshot;
- wide/desktop screenshot.

Це потрібно для richer install UI у браузерах.

## 10. Start URL

`start_url` - це URL, який відкривається, коли юзер запускає встановлену PWA з іконки.

Рішення:

- `start_url` має бути внутрішнім URL на нашому домені;
- цей URL має бути в межах PWA scope;
- при відкритті installed PWA цей URL має дати можливість зафіксувати app-open / installed-launch event;
- після цього має відбутися redirect на offer.

Приклад:

```text
/apps/{slug}/start/
```

Це аналог технічного `/play/` у сторонньому сервісі, але в нашій структурі він має бути частиною конкретного PWA App.

Чому не ставити offer URL напряму в manifest:

- `start_url` має бути в межах scope PWA;
- offer може бути на зовнішньому домені;
- нам потрібна контрольна точка для app-open event.

Очікувана поведінка:

```text
installed PWA icon click
-> /apps/{slug}/start/
-> analytics plugin restores attribution from storage
-> analytics/open event
-> analytics/redirect event
-> redirect to offer
```

Аналітика створює `click_id` / `user_id` і зберігає потрібні дані для attribution.

PWA Builder не має самостійно мапити рекламні params.

PWA Builder має:

- створити внутрішній launch URL;
- додати його в manifest як `start_url`;
- на launch URL рендерити мінімальний technical shell;
- додати DOM hook для analytics plugin, наприклад `.analytic-url` або `data-pwa-launch`;
- передати frontend context: app id, app slug, flow, чи це launch/start URL;
- дати analytics plugin можливість підготувати redirect URL і зафіксувати open/redirect event;
- мати fallback behavior, якщо analytics plugin не відпрацював.

PWA Builder не має:

- декодувати `lead_info`;
- сам вирішувати фінальний offer URL;
- сам додавати рекламні параметри;
- сам відправляти events у зовнішню аналітику.

Відкрите питання до аналітики:

- який саме event вважати app open / installed launch;
- чи потрібен окремий data attribute для launch redirect;
- скільки чекати analytics plugin перед redirect, якщо він не встиг оновити URL;
- який storage contract використовується для повторного відкриття PWA з home screen;
- чи launch shell має чекати response від analytics plugin, чи redirect має йти через already prepared `.analytic-url`.

## 11. Service Worker

Для кожного PWA App має бути service worker.

Приклад URL:

```text
/apps/{slug}/sw.js
```

Вимоги:

- коректна реєстрація;
- підтримка install/activate;
- безпечне кешування тільки HTTP/HTTPS GET requests;
- ігнорування `chrome-extension:` та інших unsupported schemes;
- не кешувати вже використаний response body;
- extension point для майбутньої push-інтеграції.

Ядро service worker має бути мінімальним.

Push логіка має додаватися тільки якщо увімкнена майбутня push-інтеграція.

## 12. Flow Behavior

Flow має бути окремим від template.

Template відповідає за вигляд.

Flow відповідає за поведінку.

Приклади flow:

- `redirect_only`;
- `install_then_redirect`;
- `redirect_or_install`;
- `show_instruction_then_redirect`;
- `installed_open_redirect`.

Один і той самий template може працювати з різними flows.

## 13. Стартовий MVP: Redirect Template / Flow

Першим шаблоном варто зробити **redirect template/flow**.

Це мінімальний PWA без повноцінного UI.

Мета:

- швидко перевірити PWA-ядро;
- перевірити manifest;
- перевірити service worker;
- перевірити install behavior;
- перевірити, що analytics plugin підхоплює CTA/redirect links і додає потрібні params;
- перевірити інтеграцію з аналітикою;
- дати менеджерам можливість запускати тести без prelanding UI.

Redirect MVP має орієнтуватися на перевірений патерн:

```text
first visit with params / lead_info
-> analytics plugin stores attribution
-> CTA/install decision
-> internal start URL
-> analytics plugin sends events
-> redirect to offer
```

PWA Builder у цьому flow відповідає за:

- manifest зі `start_url`;
- service worker;
- template/shell;
- стабільні DOM hooks;
- frontend events/hooks;
- internal start URL;
- fallback UI/redirect behavior.

Analytics plugin у цьому flow відповідає за:

- params / `lead_info`;
- `lead_uuid`, `click_id`, `user_id` або інші stable identifiers;
- localStorage/cookie attribution;
- webview escape;
- фінальний offer URL;
- events delivery;
- redirect decision.

### 13.1. Redirect Template Fields

Потрібні поля:

- App name.
- Short name.
- App icon.
- Default/Fallback Offer URL.
- Theme color.
- Background color.
- Optional loading text.
- Optional redirect delay.
- Flow behavior.

Опційно:

- screenshot;
- fallback message;
- iOS instruction text;
- Android install button text.

### 13.2. Android Behavior

Якщо доступний `beforeinstallprompt`:

1. Дати analytics plugin можливість прочитати params.
2. Дати analytics plugin можливість зберегти attribution.
3. Підготувати install prompt.
4. Показати мінімальний CTA/install UI, якщо потрібна дія юзера.
5. На CTA click показати install prompt, якщо браузер дозволяє.
6. Зберегти install decision як частину analytics-owned state, якщо це потрібно.
7. Після install decision перейти на внутрішній start URL.
8. При відкритті installed PWA браузер відкриває внутрішній launch URL з manifest `start_url`.
9. На launch URL analytics plugin фіксує open/installed-launch event.
10. Використати redirect link / CTA з класом `.analytic-url` або launch hook, який analytics plugin оновлює параметрами.
11. Зробити redirect після того, як analytics plugin підготував URL або після узгодженого fallback timeout.

Важливо:

- браузери зазвичай не дозволяють silent install без user interaction;
- не можна розраховувати, що іконка встановиться автоматично при відкритті сторінки.

### 13.3. Android Fallback

Якщо install prompt недоступний:

- redirect to offer;
- або мінімальний CTA;
- або external browser escape, якщо це ввімкнено в analytics plugin.

### 13.4. iOS Behavior

iOS не має Android-style install prompt.

Для MVP:

- одразу redirect to offer.

Інструкції або external browser escape не реалізуємо в PWA Builder.

Якщо потрібен вихід з Meta/Instagram webview:

- це має робити analytics plugin;
- PWA Builder тільки дає стабільний URL/template context.

### 13.5. Події Redirect Flow

PWA Builder має emit events, але не відправляти їх сам:

```text
visit
redirect_template_loaded
install_prompt_available
install_prompt_shown
install_prompt_accepted
install_prompt_dismissed
appinstalled
installed_launch
redirect_started
redirect_completed
redirect_failed
```

Analytics plugin вирішує, які з них реально відправляти.

### 13.6. Повний Робочий Сценарій Без Пушів І Клоаки

MVP має покривати весь PWA/redirect шлях без push та cloak logic.

Сценарій першого відкриття:

```text
1. User відкриває /apps/{slug}/?params або /apps/{slug}/?lead_info=...
2. Analytics plugin читає params / lead_info.
3. Analytics plugin створює або знаходить click_id.
4. Analytics plugin зберігає attribution у storage.
5. PWA Builder рендерить template.
6. PWA Builder підключає manifest.
7. PWA Builder реєструє service worker.
8. User тисне CTA/install.
9. Якщо Android Chrome і доступний beforeinstallprompt:
   - показати install prompt;
   - отримати install decision;
   - перейти на /apps/{slug}/start/.
10. Якщо iOS / webview / prompt недоступний:
   - перейти на /apps/{slug}/start/.
11. /apps/{slug}/start/ відкривається як technical launch shell.
12. Analytics plugin читає click_id/offer/params зі storage.
13. Analytics plugin відправляє потрібні events.
14. Analytics plugin формує final offer URL.
15. User редіректиться на offer.
```

Сценарій повторного відкриття встановленої PWA:

```text
1. User натискає PWA icon на home screen.
2. Браузер відкриває manifest.start_url.
3. manifest.start_url = /apps/{slug}/start/.
4. Landing повторно не показується.
5. Analytics plugin відновлює attribution зі storage.
6. Analytics plugin відправляє app_open / installed_launch / redirect events.
7. User редіректиться на offer.
```

Важливе рішення:

- при повторному відкритті встановленої PWA юзер не має знову бачити landing;
- PWA не може мати зовнішній offer URL як `start_url`, тому `start_url` веде на внутрішній `/start/`;
- `/start/` потрібен як технічна точка, де analytics plugin може відновити attribution і виконати redirect.

### 13.7. Frontend JS У PWA Builder

PWA Builder має мати власний frontend JS, але він не має дублювати analytics plugin.

Рекомендований поділ:

```text
assets/public/pwa-client.js
```

Працює на PWA landing/template.

Відповідає за:

- реєстрацію service worker;
- збереження `beforeinstallprompt` event;
- пошук install/CTA кнопок;
- preview guard;
- виклик install prompt при кліку, якщо він доступний;
- визначення install decision (`install` / `redirect`);
- emit frontend events;
- перехід на `startUrl`.

```text
assets/public/pwa-start.js
```

Працює тільки на `/apps/{slug}/start/`.

Відповідає за:

- emit launch/start events;
- дати analytics plugin час підготувати final URL;
- знайти `.analytic-url` або `data-pwa-launch`;
- виконати redirect після узгодженого timeout;
- використати fallback URL, якщо analytics plugin не підготував URL.

PWA Builder JS не має:

- декодувати `lead_info`;
- створювати `click_id`;
- сам відправляти events у backend;
- робити webview escape;
- керувати push subscriptions;
- реалізовувати cloak logic.

### 13.8. Frontend Config

PWA Builder має передавати на frontend стабільний config.

Приклад:

```js
window.wpPwaBuilder = {
  appId: 123,
  appSlug: 'olympus',
  manifestUrl: 'https://domain.com/apps/olympus/manifest.webmanifest',
  serviceWorkerUrl: 'https://domain.com/apps/olympus/sw.js',
  serviceWorkerScope: 'https://domain.com/apps/olympus/',
  startUrl: 'https://domain.com/apps/olympus/start/',
  fallbackUrl: 'https://fallback-offer.example/',
  flow: 'redirect_or_install',
  isPreview: false,
  isLaunch: false,
  redirectDelay: 1200
};
```

На `/start/`:

```js
window.wpPwaBuilder.isLaunch = true;
```

У preview:

```js
window.wpPwaBuilder.isPreview = true;
```

Preview behavior:

- CTA/install click не має робити redirect;
- install prompt не має відкриватися;
- webview escape не має спрацьовувати;
- events або не emit, або emit з `preview: true`;
- шаблон має бути доступний для візуальної перевірки в адмінці.

### 13.9. DOM / Event Contract З Analytics Plugin

PWA Builder має давати analytics plugin стабільні селектори і події.

CTA / redirect links:

```html
<a
  class="analytic-url"
  data-pwa-track="default_cta"
  data-pwa-install
  href="https://fallback-offer.example/"
>
  Continue
</a>
```

Launch shell:

```html
<main
  data-pwa-start
  data-app-id="123"
  data-app-slug="olympus"
  data-fallback-url="https://fallback-offer.example/"
>
  <a
    class="analytic-url"
    data-pwa-launch="1"
    data-pwa-track="installed_launch"
    href="https://fallback-offer.example/"
  ></a>
</main>
```

Frontend event format:

```js
window.dispatchEvent(new CustomEvent('wp-pwa-builder:track', {
  detail: {
    appId: 123,
    appSlug: 'olympus',
    type: 'install_prompt_accepted',
    payload: {
      installType: 'install'
    }
  }
}));
```

Analytics plugin має:

- слухати `wp-pwa-builder:track`;
- читати DOM hooks;
- оновлювати `.analytic-url`;
- зберігати attribution;
- відправляти events;
- виконувати або дозволяти redirect на offer.

### 13.10. Що Беремо З BetterLinks Як Патерн

Беремо:

- `landing -> play/start -> offer` flow;
- `manifest.start_url` на technical start URL;
- збереження attribution для повторного відкриття PWA;
- install decision перед переходом на start URL;
- ідею `first_offer_open` / першого відкриття offer;
- `sendBeacon` / `fetch keepalive` для events перед redirect;
- preview guard;
- fallback після timeout.

Не беремо:

- BetterLinks API;
- їх naming;
- пуші у PWA Builder;
- Firebase config у шаблонах;
- webview escape у PWA Builder;
- зберігання `offer_url` / `click_id` як responsibility PWA Builder;
- один великий frontend file для всього.

У нашій термінології:

```text
BetterLinks lead_uuid = наш click_id
BetterLinks /play/ = наш /apps/{slug}/start/
BetterLinks offer_url = фінальний offer URL, яким володіє analytics plugin
```


## 14. Interactive Components

Інтерактивні компоненти мають бути reusable.

Вони потрібні:

- для наших готових templates;
- для майбутнього builder.

Приклади:

```text
{{slot_machine}}
{{spin_wheel}}
{{scratch_card}}
{{quiz}}
{{fake_loader}}
{{countdown_timer}}
{{install_progress}}
```

Компоненти мають бути developer-owned.

Менеджери можуть обирати компонент або його режим через ACF-поля, але не пишуть JS-логіку.

Компоненти вставляються у developer-made templates розробником.

Приклад структури:

```text
components/
  spin-wheel/
    component.json
    render.php
    style.css
    script.js
  slot-machine/
    component.json
    render.php
    style.css
    script.js
```

Приклад `component.json`:

```json
{
  "name": "Spin Wheel",
  "placeholder": "{{spin_wheel}}",
  "styles": ["style.css"],
  "scripts": ["script.js"],
  "tracks": ["spin_start", "spin_finish"]
}
```

Component JS може dispatch events:

```js
window.dispatchEvent(new CustomEvent('wp-pwa-builder:track', {
  detail: {
    event: 'spin_finish',
    component: 'spin_wheel',
    result: 'bonus'
  }
}));
```

Analytics plugin вирішує, що з цим робити.


## 15. Traffic Params / Analytics Ownership

Traffic params мають належати analytics plugin.

Приклади:

```text
utm_source
utm_medium
utm_campaign
utm_content
campaign_id
adset_id
ad_id
campaign_name
adset_name
ad_name
placement
site_source_name
fbclid
pixel_id
pixel
click_id
c
user_id
lead_info
lead_uuid
offer_url
```

PWA Builder не має:

- сам збирати params як джерело правди;
- трансформувати params;
- мапити params в offer URL;
- відправляти params у зовнішню аналітику.
- декодувати encoded payload;
- зберігати attribution як джерело правди.

Задача analytics plugin:

- прочитати params з URL;
- декодувати `lead_info`, якщо використовується encoded payload;
- застосувати потрібні трансформації;
- створити або отримати `click_id` / `user_id`;
- зберегти потрібні дані у cookie/localStorage;
- оновити offer URL;
- відправити події в аналітичну тулзу.
- забезпечити, щоб installed PWA могла відновити потрібні дані при відкритті з home screen.

Задача PWA Builder:

- надати analytics plugin контекст PWA App;
- додати DOM hooks;
- emit frontend events;
- рендерити CTA/redirect links з класом `.analytic-url` і потрібними `data-*` атрибутами;
- рендерити internal start URL, на якому analytics plugin може виконати launch/redirect logic.

## 16. Admin UX

Менеджер має мати можливість:

- створити PWA App;
- обрати нішу;
- обрати template;
- бачити templates тільки для вибраної ніші;
- заповнити поля;
- завантажити app icon;
- завантажити screenshots;
- обрати flow;
- вказати offer URL;
- preview;
- publish / save draft через стандартні WordPress actions;
- copy link.

Duplicate/clone для MVP не реалізуємо всередині PWA Builder.

Рекомендація:

- використовувати Yoast Duplicate Post;
- додати `pwa_app` у supported post types цього плагіна.

Майбутньо:

- logs;
- history;
- change tracking.

## 17. Statuses

Для MVP варто максимально використовувати стандартні WordPress statuses:

- `draft`;
- `publish`;
- `trash`.

Це вже дає:

- save draft;
- publish;
- unpublish через draft/private;
- trash/delete.

Окремий custom campaign status варто додавати тільки якщо стандартних WP statuses недостатньо.

Можливі майбутні business statuses:

- Paused.
- Archived.
- In review.
- Rejected.

Відкрите питання:

- чи потрібен окремий status field у MVP, якщо WP вже має `draft/publish/trash`.

## 18. ACF Strategy

ACF JSON використовуємо для field groups.

Рекомендація:

- core PWA fields - окрема group;
- template-specific fields - окремі groups;
- зберігати JSON у plugin folder.

Відкрите питання:

- чи зберігати template-specific ACF JSON у `templates/{template}/acf-json/`, чи централізовано в `acf-json/`.

Початково можна централізовано.

## 19. Dev / Prod

Потрібно уникати hardcoded local URLs.

Має бути centralized environment logic:

- local;
- staging;
- production.

Від цього можуть залежати:

- manifest URL;
- service worker URL;
- asset URLs;
- pretty URLs;
- debug mode.

Domain-level settings у PWA Builder для MVP не потрібні.

Причина:

- плагін встановлюється окремо на кожен домен;
- домен у такому випадку вже є контекстом конкретної WordPress-інсталяції.

## 20. Extension Points

PWA Builder має мати hooks/filters для:

- niches;
- templates;
- OS detection;
- selected template variant;
- manifest data;
- start_url;
- service worker config;
- before render;
- after render;
- analytics frontend events;
- push injection;
- cloak decision.

## 21. Незалежна Клоака: Окреме ТЗ

Для клоаки потрібно буде окреме ТЗ.

Початкові ідеї:

- independent plugin;
- може працювати з PWA і звичайними лендами;
- правила за GEO;
- правила за OS;
- правила за browser/device;
- правила за query params;
- action:
  - allow;
  - show white page;
  - show selected WP page;
  - redirect;
  - show selected safe template;
- logs;
- debug/test mode.

PWA Builder не реалізує ці правила, але має дати integration point.

## 22. Фази Розробки

### Phase 1: Core PWA Builder + Redirect MVP

- CPT `pwa_app`.
- Basic settings.
- Niche registry.
- Template registry.
- Clean shell.
- Manifest endpoint.
- Service worker endpoint.
- Icon/screenshot fields.
- Default template.
- Redirect template.
- Redirect/install flow.
- CTA/redirect links з `.analytic-url`.
- Frontend events.
- Internal `/apps/{slug}/start/` endpoint.
- Installed launch handling.
- Basic analytics hooks/context.
- Fallback behavior, якщо analytics plugin не підключений або не підготував URL.

### Phase 2: Analytics Contract Integration

- Integration with existing analytics plugin.
- Params / `lead_info` ownership in analytics plugin.
- Visit/click/install/open/redirect events.
- Stable identifiers.
- Перевірка, що analytics plugin коректно оновлює `.analytic-url`.
- Internal launch URL у `start_url` для installed PWA.
- App-open / installed-launch event перед redirect to offer.
- Webview escape залишається в analytics plugin.

### Phase 3: OS Variants

- Android/iOS/fallback template variants.
- OS detection.
- Template assets per variant.
- Flow behavior per OS.

### Phase 4: Visual Templates

- App Store / Google Play template.
- Shared ACF fields.
- Reviews/screenshots/repeater fields.
- Template preview.

### Phase 5: Interactive Components

- Component registry.
- Slot machine.
- Spin wheel.
- Scratch card.
- Fake loader.
- Countdown timer.
- Component tracking events.

### Phase 6: Future Integrations

- Push plugin integration.
- Central push management / analytics integration.
- Independent cloak plugin integration.
- Logs/history.
- Compatibility notes for Yoast Duplicate Post.
- Додаткові інтеграції тільки якщо з'явиться реальна потреба.

## 23. Питання До Ліда

- Чи погоджуємо, що PWA Builder не відповідає за analytics/push/cloak? - так
- Чи стартуємо з redirect template як MVP? - так
- Який default behavior для Android?
- Який default behavior для iOS?
- Чи iOS має одразу редіректити, чи показувати інструкцію? - одразу редірект
- Чи installed PWA завжди має відкривати offer? - так
- Чи PWA має мати корисний UI після встановлення, чи це redirect shell? - редірект
- Чи збір/трансформацію params повністю залишаємо analytics plugin? Рекомендація: так. - так
- Чи достатньо для інтеграції CTA/redirect links з класом `.analytic-url`?
- Як має поводитися `start_url` для installed PWA з точки зору attribution? - внутрішній launch URL, який дає зафіксувати app-open / installed-launch event і потім редіректить на offer
- Чи погоджуємо модель `/apps/{slug}/start/` як аналог technical `/play/`, але без переносу analytics logic у PWA Builder?
- Чи `lead_info` / `lead_uuid` / `offer_url` повністю залишаються у зоні analytics plugin?
- Чи analytics plugin бере на себе webview escape для Meta/Instagram traffic? - так, ця логіка вже є в аналітиці
- Який fallback має бути на `/start/`, якщо analytics plugin не знайшов attribution або offer URL?
- Скільки PWA Builder має чекати analytics plugin перед fallback redirect / fallback screen?
- Чи вистачає стандартних WP statuses `draft/publish/trash` для MVP? - так
- Чи один PWA App = одна кампанія, один offer, чи один creative? - ні, можна буде робити клон і використовувати одну апку під багато кампаній
- Чи потрібні preview links? - швидше за все так
- Чи потрібні domain-level settings у PWA Builder? - ні. Плагін встановлюється окремо на кожен домен
- Чи PWA Builder має інтегруватися з існуючим split plugin для OS routing? - плагін живе окремо. не потребує додаткових інтеграцій
- Чи Android/iOS різницю вирішуємо через template variants, split plugin, чи обома способами?
- Чи interactive components робимо як developer-owned components з ACF settings? - робимо, це просто легше для нас, щоб створювати різні темплейти які можуть мати якісь спільні елементи
- Чи клоаку точно виносимо в незалежний plugin? Рекомендація: так. - так, однозначно

## 24. Поточне Рішення

Поточна рекомендована архітектура:

- PWA Builder - ядро для створення PWA.
- Analytics - окремий існуючий плагін.
- Push - окрема майбутня централізована система/інтеграція, бажано пов'язана з аналітикою.
- Cloak - окремий незалежний plugin для PWA і звичайних лендів.
- Flow окремо від template.
- Template окремо від interactive components.
- Custom HTML templates для менеджерів не плануються на поточний етап; повернутися до них можна тільки окремим рішенням після оцінки ризиків.
- Менеджери працюють через ACF-поля та готові developer-made templates/components.
- Traffic params належать analytics plugin.
- PWA Builder тільки дає hooks/events і рендерить CTA/redirect links з `.analytic-url`.
- `manifest.start_url` має вести на внутрішній `/apps/{slug}/start/`.
- `/apps/{slug}/start/` є technical launch shell для installed PWA і redirect flow.
- BetterLinks-подібну модель `landing -> play/start -> offer` беремо як патерн, але реалізуємо через наші окремі плагіни.
- `lead_info`, localStorage attribution, webview escape, offer URL і event delivery залишаються в analytics plugin.
- Стартувати з redirect template/flow + internal start endpoint.
