<p class="text-secondary updated-at">Last updated: July 16, 2026</p>

<p>
    These Terms of Service govern the use of the
    <strong>{{ config('app.name') }}</strong> panel ("the service"). By
    accessing the panel or signing in with your Google account, you are
    deemed to have accepted these terms.
</p>

<h2>1. Description of the service</h2>
<p>The panel offers the following checks:</p>
<ul>
    <li>Ranking tracking in Google search results (SERP).</li>
    <li>Visibility detection in the Google AI Overview box.</li>
    <li>Basic on-page SEO analysis (title, meta, headings, links, etc.).</li>
    <li>Google PageSpeed Insights (Lighthouse) performance/SEO/accessibility scores.</li>
</ul>

<h2>2. Access</h2>
<p>
    Any Google account can sign in and create an account; however, new
    accounts cannot access the panel's features until an admin
    approves them. An admin may revoke an account's approval or delete
    an account at any time and without stating a reason, in which case
    that account's access is terminated immediately.
</p>

<h2>3. Acceptable use</h2>
<p>By using the panel, you agree that:</p>
<ul>
    <li>
        You will only scan domains/pages that you own or have
        permission to check.
    </li>
    <li>
        You will not perform intensive/mass querying in a way that
        violates Google's terms of service, robots.txt rules, or
        applicable law.
    </li>
    <li>
        You will not use the service in a way that harms other
        systems, abuses them, or attempts to bypass their security.
    </li>
</ul>

<h2>4. Nature of results and disclaimer</h2>
<p>
    The panel fetches Google's search results page (SERP) directly
    over HTTP (not an official SERP API); therefore:
</p>
<ul>
    <li>
        Google may occasionally block automated requests
        (CAPTCHA/"unusual traffic"), in which case results may not be
        obtained.
    </li>
    <li>
        The AI Overview box is typically rendered client-side (via
        JavaScript) and varies by account/location/device, so its
        detection is <strong>best-effort</strong> in nature; an AI
        Overview that is actually visible may not be captured by the
        panel, or a result shown in the panel may differ from a
        real-time search.
    </li>
    <li>
        Because Google's page structure changes frequently, the
        parsing logic may become outdated over time.
    </li>
</ul>
<p>
    The service is provided "as-is"; no guarantee of accuracy,
    uninterrupted operation, or fitness for a particular purpose is
    given. The site owner cannot be held liable for the consequences
    of business/marketing decisions made based on the panel's results.
</p>

<h2>5. Dependency on third-party services</h2>
<p>
    The service depends on Google OAuth, Google Search, and the Google
    PageSpeed Insights API. Outages, changes, or quota restrictions in
    these services (e.g., the low PSI quota when used without an API
    key) may affect the panel's functionality.
</p>

<h2>6. Modification or termination of the service</h2>
<p>
    The site owner may modify, temporarily suspend, or permanently
    terminate the panel without prior notice.
</p>

<h2>7. Changes</h2>
<p>
    These terms may be updated from time to time; the current version
    is always published on this page.
</p>

<h2>8. Contact</h2>
<p>
    For questions about these terms, you can contact
    @if (config('mail.from.address') && config('mail.from.address') !== 'hello@example.com')
        <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>
    @else
        the panel administrator
    @endif
    .
</p>
