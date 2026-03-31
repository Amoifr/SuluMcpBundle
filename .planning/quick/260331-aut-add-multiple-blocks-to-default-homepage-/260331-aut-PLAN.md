---
type: quick
scope: dev application
files_modified:
  - dev/config/templates/pages/homepage.xml
  - dev/config/templates/pages/default.xml
  - dev/templates/pages/homepage.html.twig
  - dev/templates/pages/default.html.twig
  - dev/templates/base.html.twig
  - dev/templates/blocks/heading.html.twig
  - dev/templates/blocks/text.html.twig
  - dev/templates/blocks/image.html.twig
  - dev/templates/blocks/quote.html.twig
  - dev/templates/blocks/text_with_image.html.twig
  - dev/src/DataFixtures/PageFixtures.php
  - dev/package.json
  - dev/tailwind.config.js
  - dev/postcss.config.js
  - dev/assets/website/css/app.css
  - dev/assets/website/js/app.js
  - dev/webpack.config.website.js
autonomous: true
---

<objective>
Add block-based content to the dev Sulu application: define 5 block types in both page templates (homepage.xml, default.xml), implement Twig templates with Tailwind CSS for rendering, set up Tailwind for the website frontend, and create DataFixtures that seed pages with varied block content.

Purpose: Provide a rich demo environment for testing MCP content tools against real block-based templates.
Output: Working block templates, styled Twig rendering, and fixture-seeded pages.
</objective>

<context>
@dev/config/templates/pages/homepage.xml
@dev/config/templates/pages/default.xml
@dev/templates/pages/homepage.html.twig
@dev/templates/pages/default.html.twig
@dev/templates/base.html.twig
@dev/src/DataFixtures/AppFixtures.php
@dev/vendor/sulu/sulu/config/templates/pages/example.xml (block XML pattern reference)
@dev/vendor/sulu/sulu/templates/pages/example.html.twig (block Twig rendering reference)
@dev/vendor/sulu/sulu/packages/page/tests/Traits/CreatePageTrait.php (fixture message bus pattern)
@dev/vendor/sulu/sulu/packages/page/src/Application/Message/CreatePageMessage.php
@dev/vendor/sulu/sulu/packages/page/src/Application/Message/ApplyWorkflowTransitionPageMessage.php

Key references:
- Webspace key: `website` (from dev/config/webspaces/website.xml)
- CreatePageMessage(webspaceKey, parentId, data) where data must include `locale`
- HOMEPAGE_PARENT_ID = `'homepage'` (from CreatePageMessageHandler) for child pages
- Homepage itself is modified via ModifyPageMessage with identifier `['uuid' => $homepageUuid]`
- EnableFlushStamp wraps Envelope for Doctrine flush
- WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH for publishing
- Block data format in $data array: `'blocks' => [['type' => 'heading', 'title' => '...'], ...]`
- Existing admin assets in dev/assets/admin/ — website assets go in dev/assets/website/ (separate)
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add block types to XML templates and set up Tailwind + Twig rendering</name>
  <files>
    dev/config/templates/pages/homepage.xml
    dev/config/templates/pages/default.xml
    dev/templates/pages/homepage.html.twig
    dev/templates/pages/default.html.twig
    dev/templates/base.html.twig
    dev/templates/blocks/heading.html.twig
    dev/templates/blocks/text.html.twig
    dev/templates/blocks/image.html.twig
    dev/templates/blocks/quote.html.twig
    dev/templates/blocks/text_with_image.html.twig
    dev/package.json
    dev/tailwind.config.js
    dev/postcss.config.js
    dev/assets/website/css/app.css
    dev/assets/website/js/app.js
  </files>
  <action>
    **1. Add block property to both homepage.xml and default.xml** — after the existing `article` property, add a `<block>` element with these 5 types:

    ```xml
    <block name="blocks" default-type="text" minOccurs="0">
        <meta>
            <title lang="en">Content Blocks</title>
            <title lang="de">Inhaltsblöcke</title>
        </meta>
        <types>
            <type name="heading">
                <meta>
                    <title lang="en">Heading</title>
                    <title lang="de">Überschrift</title>
                </meta>
                <properties>
                    <property name="title" type="text_line" mandatory="true">
                        <meta>
                            <title lang="en">Heading Text</title>
                            <title lang="de">Überschrift</title>
                        </meta>
                    </property>
                </properties>
            </type>
            <type name="text">
                <meta>
                    <title lang="en">Text</title>
                    <title lang="de">Text</title>
                </meta>
                <properties>
                    <property name="content" type="text_editor">
                        <meta>
                            <title lang="en">Content</title>
                            <title lang="de">Inhalt</title>
                        </meta>
                    </property>
                </properties>
            </type>
            <type name="image">
                <meta>
                    <title lang="en">Image</title>
                    <title lang="de">Bild</title>
                </meta>
                <properties>
                    <property name="image" type="media_selection" mandatory="true">
                        <meta>
                            <title lang="en">Image</title>
                            <title lang="de">Bild</title>
                        </meta>
                        <params>
                            <param name="types" value="image"/>
                        </params>
                    </property>
                    <property name="caption" type="text_line">
                        <meta>
                            <title lang="en">Caption</title>
                            <title lang="de">Bildunterschrift</title>
                        </meta>
                    </property>
                </properties>
            </type>
            <type name="quote">
                <meta>
                    <title lang="en">Quote</title>
                    <title lang="de">Zitat</title>
                </meta>
                <properties>
                    <property name="text" type="text_editor" mandatory="true">
                        <meta>
                            <title lang="en">Quote Text</title>
                            <title lang="de">Zitattext</title>
                        </meta>
                    </property>
                    <property name="attribution" type="text_line">
                        <meta>
                            <title lang="en">Attribution</title>
                            <title lang="de">Zuschreibung</title>
                        </meta>
                    </property>
                </properties>
            </type>
            <type name="text_with_image">
                <meta>
                    <title lang="en">Text with Image</title>
                    <title lang="de">Text mit Bild</title>
                </meta>
                <properties>
                    <property name="content" type="text_editor" mandatory="true">
                        <meta>
                            <title lang="en">Content</title>
                            <title lang="de">Inhalt</title>
                        </meta>
                    </property>
                    <property name="image" type="media_selection" mandatory="true">
                        <meta>
                            <title lang="en">Image</title>
                            <title lang="de">Bild</title>
                        </meta>
                        <params>
                            <param name="types" value="image"/>
                        </params>
                    </property>
                </properties>
            </type>
        </types>
    </block>
    ```

    **2. Set up Tailwind CSS for website frontend.**

    Create `dev/package.json` (root level, separate from admin):
    ```json
    {
      "name": "sulu-mcp-dev-website",
      "private": true,
      "scripts": {
        "dev": "npx tailwindcss -i ./assets/website/css/app.css -o ./public/build/website/app.css --watch",
        "build": "npx tailwindcss -i ./assets/website/css/app.css -o ./public/build/website/app.css --minify"
      },
      "devDependencies": {
        "tailwindcss": "^3.4"
      }
    }
    ```

    Create `dev/tailwind.config.js`:
    ```js
    module.exports = {
      content: ['./templates/**/*.html.twig'],
      theme: { extend: {} },
      plugins: [],
    }
    ```

    Create `dev/assets/website/css/app.css`:
    ```css
    @tailwind base;
    @tailwind components;
    @tailwind utilities;
    ```

    Run `cd dev && npm install && npm run build` to generate the CSS output.

    **3. Update base.html.twig** — add Tailwind CSS link in the `<head>` style block:
    ```twig
    {% block style %}
        <link rel="stylesheet" href="/build/website/app.css">
    {% endblock %}
    ```

    **4. Update homepage.html.twig and default.html.twig** — replace simple article rendering with block iteration. Both templates share the same structure:
    ```twig
    {% extends 'base.html.twig' %}

    {% block content %}
        <div class="max-w-4xl mx-auto px-4 py-8">
            <h1 class="text-4xl font-bold mb-8">{{ content.title }}</h1>

            {% if content.article %}
                <div class="prose prose-lg mb-12">
                    {{ content.article|raw }}
                </div>
            {% endif %}

            {% if content.blocks is defined %}
                {% for block in content.blocks %}
                    {% include 'blocks/' ~ block.type ~ '.html.twig' with { block: block } %}
                {% endfor %}
            {% endif %}
        </div>
    {% endblock %}
    ```

    **5. Create block partial templates** in `dev/templates/blocks/`:

    `heading.html.twig`:
    ```twig
    <div class="my-8">
        <h2 class="text-3xl font-semibold text-gray-900">{{ block.title }}</h2>
    </div>
    ```

    `text.html.twig`:
    ```twig
    <div class="prose prose-lg my-6">
        {{ block.content|raw }}
    </div>
    ```

    `image.html.twig`:
    ```twig
    <figure class="my-8">
        {% for media in block.image %}
            <img src="{{ media.thumbnails['sulu-400x400'] }}"
                 alt="{{ media.title }}"
                 class="rounded-lg shadow-md w-full max-w-2xl" />
        {% endfor %}
        {% if block.caption is defined and block.caption %}
            <figcaption class="mt-2 text-sm text-gray-500 italic">{{ block.caption }}</figcaption>
        {% endif %}
    </figure>
    ```

    `quote.html.twig`:
    ```twig
    <blockquote class="my-8 border-l-4 border-blue-500 pl-6 py-2">
        <div class="prose prose-lg italic text-gray-700">{{ block.text|raw }}</div>
        {% if block.attribution is defined and block.attribution %}
            <footer class="mt-2 text-sm text-gray-500">&mdash; {{ block.attribution }}</footer>
        {% endif %}
    </blockquote>
    ```

    `text_with_image.html.twig`:
    ```twig
    <div class="my-8 flex flex-col md:flex-row gap-8 items-start">
        <div class="prose prose-lg flex-1">
            {{ block.content|raw }}
        </div>
        <div class="w-full md:w-1/3 flex-shrink-0">
            {% for media in block.image %}
                <img src="{{ media.thumbnails['sulu-400x400'] }}"
                     alt="{{ media.title }}"
                     class="rounded-lg shadow-md w-full" />
            {% endfor %}
        </div>
    </div>
    ```
  </action>
  <verify>
    cd dev && php bin/console cache:clear && php bin/console debug:config sulu_page templates 2>&1 | head -20
    Verify both homepage and default templates show up with the blocks property. Also verify `public/build/website/app.css` exists after npm build.
  </verify>
  <done>
    - homepage.xml and default.xml each have a `blocks` property with 5 block types (heading, text, image, quote, text_with_image)
    - Tailwind CSS is installed and compiled to public/build/website/app.css
    - base.html.twig links the stylesheet
    - homepage.html.twig and default.html.twig render blocks via partials
    - 5 block partial templates exist in dev/templates/blocks/
  </done>
</task>

<task type="auto">
  <name>Task 2: Create DataFixtures for seeding pages with block content</name>
  <files>
    dev/src/DataFixtures/PageFixtures.php
  </files>
  <action>
    Create `dev/src/DataFixtures/PageFixtures.php` as a Doctrine fixture that uses Sulu's message bus to create pages with block content.

    The fixture class should:
    1. Inject `MessageBusInterface` via constructor (service id: `sulu_message_bus`, autowire with `#[Target('sulu_message_bus')]` attribute or use `#[Autowire(service: 'sulu_message_bus')]`)
    2. Use `Sulu\Page\Application\Message\CreatePageMessage` with webspaceKey `'website'`
    3. Use `Sulu\Page\Application\MessageHandler\CreatePageMessageHandler::HOMEPAGE_PARENT_ID` (`'homepage'`) as parentId for child pages
    4. Wrap messages in `Envelope` with `EnableFlushStamp`
    5. After create, publish pages using `ApplyWorkflowTransitionPageMessage` with `WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH`

    Create these pages:

    **Page 1: "About Us" (default template)**
    - title: "About Us"
    - template: "default"
    - url: "/about"
    - article: "<p>Learn more about our company and mission.</p>"
    - blocks:
      - { type: "heading", title: "Our Story" }
      - { type: "text", content: "<p>Founded in 2020, we set out to make content management smarter. Our team believes AI should empower content creators, not replace them.</p>" }
      - { type: "quote", text: "<p>The best content management is invisible — it gets out of the way and lets creators create.</p>", attribution: "Our Founder" }
      - { type: "text", content: "<p>Today we serve hundreds of organizations worldwide, helping them publish content faster and more consistently.</p>" }

    **Page 2: "Services" (default template)**
    - title: "Our Services"
    - template: "default"
    - url: "/services"
    - article: ""
    - blocks:
      - { type: "heading", title: "What We Offer" }
      - { type: "text", content: "<p>We provide a range of content management and AI integration services.</p>" }
      - { type: "heading", title: "Content Strategy" }
      - { type: "text", content: "<p>Our content strategists help you define your voice, plan your editorial calendar, and measure impact.</p>" }
      - { type: "heading", title: "AI Integration" }
      - { type: "text", content: "<p>We connect AI assistants directly to your CMS so they can create, edit, and publish on-brand content.</p>" }

    **Page 3: "Blog" (default template)**
    - title: "Blog"
    - template: "default"
    - url: "/blog"
    - article: "<p>Latest news and insights from our team.</p>"
    - blocks:
      - { type: "heading", title: "AI-Powered Content Creation" }
      - { type: "text", content: "<p>Artificial intelligence is transforming how organizations create and manage content. In this post, we explore the latest trends and best practices for AI-assisted content workflows.</p>" }
      - { type: "quote", text: "<p>AI does not replace the human voice — it amplifies it.</p>", attribution: "Content Team" }

    The data array for CreatePageMessage should follow the pattern:
    ```php
    $data = [
        'locale' => 'en',
        'title' => 'About Us',
        'template' => 'default',
        'url' => '/about',
        'article' => '<p>...</p>',
        'blocks' => [
            ['type' => 'heading', 'title' => 'Our Story'],
            ['type' => 'text', 'content' => '<p>...</p>'],
            // ...
        ],
    ];
    ```

    Extract the page UUID from the HandledStamp result (`$page->getUuid()`) for the publish step.

    Use `getDependencies()` to run after AppFixtures if needed, or remove the dependency if AppFixtures is empty.

    Do NOT modify the existing `AppFixtures.php`.
  </action>
  <verify>
    cd dev && php bin/console lint:container 2>&1 | tail -5 && php bin/console doctrine:fixtures:load --append --no-interaction 2>&1
  </verify>
  <done>
    - PageFixtures.php exists and is a valid Symfony service
    - Running `doctrine:fixtures:load --append` creates 3 child pages under homepage
    - Each page has block content with varied block types
    - All pages are published (workflow transition applied)
    - Pages use the "website" webspace and "en" locale
  </done>
</task>

</tasks>

<verification>
1. `cd dev && php bin/console cache:clear` succeeds (templates valid)
2. `cd dev && php bin/console doctrine:fixtures:load --append --no-interaction` creates pages
3. `cd dev && php bin/console sulu:page:list website en 2>&1` or equivalent shows the seeded pages
4. Visit http://127.0.0.1:8000/about — page renders with blocks styled by Tailwind
</verification>

<success_criteria>
- Both page templates define 5 block types: heading, text, image, quote, text_with_image
- Twig partials render each block type with Tailwind utility classes
- Tailwind CSS is compiled and linked from base template
- Fixtures create 3 pages with diverse block content, all published
- No errors on cache:clear or fixture loading
</success_criteria>
