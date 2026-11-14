#!/usr/bin/env bash
# Builds the GitHub Release body for a tag from CHANGELOG.md and
# CHANGELOG.tr.md, plus the Docker pull command and a compare link.
#
# Usage: .github/scripts/release-notes.sh v1.2.3 [image] [repo]
#   image defaults to ghcr.io/bmericc/seo-ai-checker
#   repo  defaults to bmericc/seo-ai-checker
# Exits non-zero when CHANGELOG.md has no "## [1.2.3]" section.
set -euo pipefail

tag="${1:?usage: release-notes.sh <tag> [image] [repo]}"
image="${2:-ghcr.io/bmericc/seo-ai-checker}"
repo="${3:-bmericc/seo-ai-checker}"
version="${tag#v}"
root="$(cd "$(dirname "$0")/../.." && pwd)"

# Prints the body of "## [version] ..." up to the next "## " heading,
# without leading/trailing blank lines.
section() {
    awk -v v="$version" '
        index($0, "## [" v "]") == 1 { on = 1; next }
        on && /^## / { exit }
        on && /^\[[^]]+\]: / { next }   # link reference definitions at EOF
        on { print }
    ' "$1" | sed -e '/./,$!d' | sed -e ':a' -e '/^\n*$/{$d;N;ba' -e '}'
}

en="$(section "$root/CHANGELOG.md")"
if [ -z "$en" ]; then
    echo "CHANGELOG.md has no section for [$version]" >&2
    exit 1
fi
tr="$(section "$root/CHANGELOG.tr.md" || true)"

previous="$(git -C "$root" tag --list 'v*.*.*' --sort=-v:refname \
    | awk -v t="$tag" 'found { print; exit } $0 == t { found = 1 }')"

printf '%s\n' "$en"

if [ -n "$tr" ]; then
    printf '\n<details>\n<summary>🇹🇷 Türkçe</summary>\n\n%s\n\n</details>\n' "$tr"
fi

printf '\n## Docker image\n\n```bash\ndocker pull %s:%s\n```\n' "$image" "$version"

if [ -n "$previous" ]; then
    printf '\n**Full Changelog**: https://github.com/%s/compare/%s...%s\n' "$repo" "$previous" "$tag"
else
    printf '\n**Full Changelog**: https://github.com/%s/commits/%s\n' "$repo" "$tag"
fi
