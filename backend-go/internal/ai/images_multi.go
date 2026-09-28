package ai

import (
	"context"
	"image"
	"image/color"
	"image/draw"
	_ "image/gif" // decodificadores
	"image/jpeg"
	_ "image/png" //
	"os"
	"path/filepath"

	"washiviana/backend/internal/textutil"
)

// GenerateImagesMulti gera uma imagem base e cria recortes 1:1 e 9:16 (JPEG).
// Porta gerarImagensMultiplosFormatos()/criarVersaoRecortada().
// Divergência: não aplica o otimizador (resize GD) — apenas recorta.
func (s *Service) GenerateImagesMulti(ctx context.Context, prompt, uploadDir, uploadURL string) (map[string]any, error) {
	base, err := s.GenerateImage(ctx, prompt, uploadDir, uploadURL)
	if err != nil {
		return nil, err
	}
	basePath := filepath.Join(uploadDir, base.Filename)
	f, err := os.Open(basePath)
	if err != nil {
		return nil, err
	}
	src, _, err := image.Decode(f)
	_ = f.Close()
	if err != nil {
		return nil, err
	}

	baseID := textutil.Uniqid()
	out := map[string]any{"success": true}

	specs := []struct {
		rw, rh, maxW, maxH  int
		prefix, key, urlKey string
	}{
		{1, 1, 1200, 1200, "ai_1x1_", "imagem_1x1", "imagem_1x1_url"},
		{9, 16, 1080, 1920, "ai_9x16_", "imagem_9x16", "imagem_9x16_url"},
	}
	for _, sp := range specs {
		cropped := cropToRatio(src, sp.rw, sp.rh)
		name := sp.prefix + baseID + ".jpg"
		dst := filepath.Join(uploadDir, name)
		if err := writeJPEG(dst, cropped, 90); err != nil {
			continue
		}
		// Otimização (resize + recompressão), como o PHP.
		_ = optimizeJPEGInPlace(dst, sp.maxW, sp.maxH, 500, 85)
		out[sp.key] = name
		out[sp.urlKey] = trimSlash(uploadURL) + "/" + name
	}

	// Remove a imagem base temporária (como o PHP).
	_ = os.Remove(basePath)

	if _, ok := out["imagem_1x1"]; !ok {
		if _, ok2 := out["imagem_9x16"]; !ok2 {
			return map[string]any{"success": false, "message": "Erro ao processar formatos da imagem"}, nil
		}
	}
	out["note"] = "Mesma imagem base em formatos diferentes"
	return out, nil
}

// cropToRatio faz recorte central para o aspect ratio (ratioW:ratioH).
func cropToRatio(src image.Image, ratioW, ratioH int) image.Image {
	b := src.Bounds()
	w := b.Dx()
	h := b.Dy()
	if w <= 0 || h <= 0 || ratioW <= 0 || ratioH <= 0 {
		return src
	}
	desejado := float64(ratioW) / float64(ratioH)
	atual := float64(w) / float64(h)

	var newW, newH, x, y int
	if atual > desejado {
		newH = h
		newW = int(float64(h) * desejado)
		x = (w - newW) / 2
		y = 0
	} else {
		newW = w
		newH = int(float64(w) / desejado)
		x = 0
		y = (h - newH) / 2
	}
	if newW < 1 {
		newW = 1
	}
	if newH < 1 {
		newH = 1
	}

	dst := image.NewRGBA(image.Rect(0, 0, newW, newH))
	draw.Draw(dst, dst.Bounds(), &image.Uniform{C: color.White}, image.Point{}, draw.Src)
	draw.Draw(dst, dst.Bounds(), src, image.Point{X: b.Min.X + x, Y: b.Min.Y + y}, draw.Src)
	return dst
}

func writeJPEG(path string, img image.Image, quality int) error {
	f, err := os.Create(path)
	if err != nil {
		return err
	}
	defer f.Close()
	return jpeg.Encode(f, img, &jpeg.Options{Quality: quality})
}

func atoiSimple(s string) int {
	n := 0
	for _, r := range s {
		if r < '0' || r > '9' {
			return n
		}
		n = n*10 + int(r-'0')
	}
	return n
}

func trimSlash(s string) string {
	for len(s) > 0 && s[len(s)-1] == '/' {
		s = s[:len(s)-1]
	}
	return s
}
