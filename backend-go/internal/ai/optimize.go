package ai

import (
	"image"
	"image/color"
	"os"
)

// optimizeJPEGInPlace redimensiona (sem ampliar) e recomprime JPEG ajustando a
// qualidade até caber em maxSizeKB. Porta otimizarImagemParaRedesSociais().
func optimizeJPEGInPlace(path string, maxW, maxH, maxSizeKB, quality int) error {
	f, err := os.Open(path)
	if err != nil {
		return err
	}
	src, _, err := image.Decode(f)
	_ = f.Close()
	if err != nil {
		return err
	}
	b := src.Bounds()
	w, h := b.Dx(), b.Dy()
	newW, newH := fitDimensions(w, h, maxW, maxH)
	img := src
	if newW != w || newH != h {
		img = resizeBilinear(src, newW, newH)
	}

	q := quality
	for attempt := 0; ; attempt++ {
		if err := writeJPEG(path, img, q); err != nil {
			return err
		}
		if st, err := os.Stat(path); err == nil {
			if float64(st.Size())/1024 <= float64(maxSizeKB) {
				break
			}
		}
		if q <= 60 || attempt >= 4 {
			break
		}
		q -= 10
	}
	return nil
}

// fitDimensions calcula as dimensões finais mantendo o aspect ratio (sem ampliar).
func fitDimensions(w, h, maxW, maxH int) (int, int) {
	if w <= 0 || h <= 0 || maxW <= 0 || maxH <= 0 {
		return w, h
	}
	ratio := float64(w) / float64(h)
	targetRatio := float64(maxW) / float64(maxH)
	var newW, newH int
	if ratio > targetRatio {
		newW = maxW
		newH = int(float64(maxW) / ratio)
	} else {
		newH = maxH
		newW = int(float64(maxH) * ratio)
	}
	if newW > w && newH > h {
		newW = w
		newH = h
	}
	if newW < 1 {
		newW = 1
	}
	if newH < 1 {
		newH = 1
	}
	return newW, newH
}

// resizeBilinear redimensiona para newW x newH (interpolação bilinear simples).
func resizeBilinear(src image.Image, newW, newH int) image.Image {
	b := src.Bounds()
	w := b.Dx()
	h := b.Dy()
	dst := image.NewRGBA(image.Rect(0, 0, newW, newH))
	if w == 0 || h == 0 {
		return dst
	}
	for y := 0; y < newH; y++ {
		sy := (float64(y) + 0.5) * float64(h) / float64(newH)
		y0 := int(sy)
		if y0 >= h {
			y0 = h - 1
		}
		y1 := y0 + 1
		if y1 >= h {
			y1 = h - 1
		}
		fy := sy - float64(y0)
		for x := 0; x < newW; x++ {
			sx := (float64(x) + 0.5) * float64(w) / float64(newW)
			x0 := int(sx)
			if x0 >= w {
				x0 = w - 1
			}
			x1 := x0 + 1
			if x1 >= w {
				x1 = w - 1
			}
			fx := sx - float64(x0)

			r00, g00, b00, a00 := src.At(b.Min.X+x0, b.Min.Y+y0).RGBA()
			r10, g10, b10, a10 := src.At(b.Min.X+x1, b.Min.Y+y0).RGBA()
			r01, g01, b01, a01 := src.At(b.Min.X+x0, b.Min.Y+y1).RGBA()
			r11, g11, b11, a11 := src.At(b.Min.X+x1, b.Min.Y+y1).RGBA()

			lerp := func(p00, p10, p01, p11 uint32) uint8 {
				top := float64(p00)*(1-fx) + float64(p10)*fx
				bot := float64(p01)*(1-fx) + float64(p11)*fx
				v := top*(1-fy) + bot*fy
				return uint8(v / 257)
			}
			dst.SetRGBA(x, y, color.RGBA{
				R: lerp(r00, r10, r01, r11),
				G: lerp(g00, g10, g01, g11),
				B: lerp(b00, b10, b01, b11),
				A: lerp(a00, a10, a01, a11),
			})
		}
	}
	return dst
}
