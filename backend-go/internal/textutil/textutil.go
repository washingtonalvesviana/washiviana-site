// Package textutil replica helpers de texto do PHP (sanitize/generateSlug) para
// manter paridade de contrato no modo strangler.
package textutil

import (
	"crypto/rand"
	"encoding/hex"
	"regexp"
	"strings"
	"unicode"

	"golang.org/x/text/unicode/norm"
)

// Uniqid aproxima uniqid() do PHP para sufixos de slug (13 chars hex).
func Uniqid() string {
	b := make([]byte, 8)
	if _, err := rand.Read(b); err != nil {
		return "0000000000000"
	}
	return hex.EncodeToString(b)[:13]
}

// Sanitize replica sanitize() do PHP:
// htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8').
func Sanitize(data string) string {
	s := strings.TrimSpace(data)
	s = stripTagsRe.ReplaceAllString(s, "")
	return htmlSpecialChars(s)
}

var (
	stripTagsRe  = regexp.MustCompile(`<[^>]*>`)
	nonAlnumRe   = regexp.MustCompile(`[^\pL\d]+`)
	nonWordRe    = regexp.MustCompile(`[^-\w]+`)
	multiDashRe  = regexp.MustCompile(`-+`)
	artInvalidRe = regexp.MustCompile(`[^a-z0-9\s-]`)
	artSpaceRe   = regexp.MustCompile(`[\s-]+`)
	htmlReplacer = strings.NewReplacer(
		"&", "&amp;",
		"<", "&lt;",
		">", "&gt;",
		"\"", "&quot;",
		"'", "&#039;",
	)
)

func htmlSpecialChars(s string) string { return htmlReplacer.Replace(s) }

// latinMap cobre letras não decomponíveis que o iconv//TRANSLIT do PHP converte.
var latinMap = map[rune]string{
	'ø': "o", 'Ø': "O",
	'æ': "ae", 'Æ': "AE",
	'œ': "oe", 'Œ': "OE",
	'ß': "ss",
	'đ': "d", 'Đ': "D",
	'ł': "l", 'Ł': "L",
	'ð': "d", 'Ð': "D",
	'þ': "th", 'Þ': "TH",
}

// Slugify replica generateSlug() do PHP.
func Slugify(text string) string {
	s := nonAlnumRe.ReplaceAllString(text, "-")
	s = transliterate(s)
	s = nonWordRe.ReplaceAllString(s, "")
	s = strings.Trim(s, "-")
	s = multiDashRe.ReplaceAllString(s, "-")
	s = strings.ToLower(s)
	if s == "" {
		return "n-a"
	}
	return s
}

// transliterate aproxima o iconv('utf-8','us-ascii//TRANSLIT'): decompõe (NFD),
// remove marcas diacríticas e mapeia um pequeno conjunto de letras especiais.
func transliterate(s string) string {
	var b strings.Builder
	for _, r := range norm.NFD.String(s) {
		if unicode.Is(unicode.Mn, r) {
			continue
		}
		if r < 128 {
			b.WriteRune(r)
			continue
		}
		if rep, ok := latinMap[r]; ok {
			b.WriteString(rep)
			continue
		}
		b.WriteRune(r)
	}
	return b.String()
}

var artAccentReplacer = strings.NewReplacer(
	"á", "a", "à", "a", "ã", "a", "â", "a", "ä", "a",
	"é", "e", "è", "e", "ê", "e", "ë", "e",
	"í", "i", "ì", "i", "î", "i", "ï", "i",
	"ó", "o", "ò", "o", "õ", "o", "ô", "o", "ö", "o",
	"ú", "u", "ù", "u", "û", "u", "ü", "u",
	"ç", "c",
)

// SlugifyArticle replica gerarSlug() de api/artigos.php (diferente de Slugify).
func SlugifyArticle(texto string) string {
	s := strings.ToLower(texto)
	s = artAccentReplacer.Replace(s)
	s = artInvalidRe.ReplaceAllString(s, "")
	s = artSpaceRe.ReplaceAllString(s, "-")
	return strings.Trim(s, "-")
}

var (
	i18nAccent    = strings.NewReplacer(
		"á", "a", "à", "a", "ã", "a", "â", "a", "ä", "a",
		"é", "e", "è", "e", "ê", "e", "ë", "e",
		"í", "i", "ì", "i", "î", "i", "ï", "i",
		"ó", "o", "ò", "o", "õ", "o", "ô", "o", "ö", "o",
		"ú", "u", "ù", "u", "û", "u", "ü", "u",
		"ç", "c", "ñ", "n",
	)
	i18nNonAlnum = regexp.MustCompile(`[^a-z0-9]+`)
	i18nMultiDash = regexp.MustCompile(`-{2,}`)
)

// SlugifyI18n replica slugify() de api/i18n_seo.php.
func SlugifyI18n(s string) string {
	s = strings.ToLower(strings.TrimSpace(s))
	s = i18nAccent.Replace(s)
	s = i18nNonAlnum.ReplaceAllString(s, "-")
	s = strings.Trim(s, "-")
	s = i18nMultiDash.ReplaceAllString(s, "-")
	if s == "" {
		return "item"
	}
	return s
}

// Ucfirst replica ucfirst() do PHP (apenas o primeiro caractere).
func Ucfirst(s string) string {
	if s == "" {
		return s
	}
	r := []rune(s)
	r[0] = unicode.ToUpper(r[0])
	return string(r)
}
