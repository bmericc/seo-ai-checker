<p class="text-secondary updated-at">Last updated: July 16, 2026</p>

<p>
    This Privacy Policy describes the personal data collected and
    processed by <strong>{{ config('app.name') }}</strong> ("the panel",
    "the service"). Any Google account can sign in and create an
    account; however, new accounts cannot access the panel's features
    (domain/keyword management) until an admin approves them.
</p>

<h2>1. Who processes the data?</h2>
<p>
    The operator of this panel is the site owner who uses it to track
    their own domain(s) and keywords. For questions, you can contact
    @if (config('mail.from.address') && config('mail.from.address') !== 'hello@example.com')
        <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>
    @else
        the panel administrator
    @endif
    .
</p>

<h2>2. What data is collected?</h2>
<ul>
    <li>
        <strong>Google account information</strong> — when you sign in
        with Google, the name and email address provided by Google are
        stored to create and identify your account.
    </li>
    <li>
        <strong>Session data</strong> — session and CSRF cookies are
        used to keep you signed in. These cookies are not used for
        marketing or tracking purposes.
    </li>
    <li>
        <strong>Account approval status</strong> — whether your account
        has been approved by an admin and whether you have admin
        privileges.
    </li>
    <li>
        <strong>Records you create in the panel</strong> — the domains
        and keywords you add, and the result of every "Check" run
        (Google search ranking, AI Overview status, on-page SEO
        analysis, Lighthouse scores) are stored in the database. These
        records are shared across all approved users (they are not
        user-specific).
    </li>
</ul>
<p>
    The panel does not collect personal data from visitors or
    third-party websites; it only analyzes the publicly available
    search results and page content of the domains/URLs you specify.
</p>

<h2>3. What is the data used for?</h2>
<ul>
    <li>Authentication and authorization of access to the panel.</li>
    <li>
        Running the SEO/AI Overview checks you request and showing you
        their history.
    </li>
</ul>
<p>Data is not used for advertising, profiling, or sale to third parties.</p>

<h2>4. Third-party services</h2>
<ul>
    <li>
        <strong>Google OAuth (Sign in with Google)</strong> — used for
        authentication; Google's own
        <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">privacy policy</a>
        applies.
    </li>
    <li>
        <strong>Google Search</strong> — target keywords are fetched
        from Google's publicly available search results page for
        ranking and AI Overview detection.
    </li>
    <li>
        <strong>Google PageSpeed Insights API</strong> — for Lighthouse
        scores, the URL of the page you check is sent to Google's
        PageSpeed Insights service.
    </li>
</ul>

<h2>5. Data retention period</h2>
<p>
    Account and record data are kept until you or an admin delete
    them. When you delete a domain or keyword record, all of its
    associated check history is also permanently deleted. If an admin
    revokes your account's approval, your access to the panel ends
    immediately; however, you must contact an admin to have your
    account record fully deleted.
</p>

<h2>6. Data security</h2>
<p>
    Access to the panel's features is restricted to accounts approved
    by an admin, over HTTPS. The application code and database are
    hosted in a non-public server environment.
</p>

<h2>7. Your rights</h2>
<p>
    You can use the contact channel above to request access to,
    correction of, or deletion of the data held about you.
</p>

<h2>8. Changes</h2>
<p>
    This policy may be updated from time to time; significant changes
    will be published on this page.
</p>
