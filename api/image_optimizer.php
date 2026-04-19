<?php
/**
 * Funções de otimização de imagens para LinkedIn/Open Graph
 * 
 * Dimensões recomendadas:
 * - LinkedIn: 1200x627px (ratio 1.91:1) ou 1200x1200px (1:1)
 * - Open Graph: 1200x630px (ratio 1.91:1)
 * - Tamanho máximo: 500KB
 */

/**
 * Otimiza uma imagem para uso em redes sociais
 * 
 * @param string $sourcePath Caminho da imagem original
 * @param string $destPath Caminho de destino (opcional, sobrescreve se não fornecido)
 * @param int $maxWidth Largura máxima (padrão: 1200)
 * @param int $maxHeight Altura máxima (padrão: 630)
 * @param int $maxSizeKB Tamanho máximo em KB (padrão: 500)
 * @param int $quality Qualidade JPEG (padrão: 85)
 * @return array ['success' => bool, 'message' => string, 'path' => string, 'size' => int]
 */
function otimizarImagemParaRedesSociais($sourcePath, $destPath = null, $maxWidth = 1200, $maxHeight = 630, $maxSizeKB = 500, $quality = 85) {
    try {
        // Verificar se o arquivo existe
        if (!file_exists($sourcePath)) {
            return ['success' => false, 'message' => 'Arquivo não encontrado'];
        }
        
        // Obter informações da imagem
        $imageInfo = getimagesize($sourcePath);
        if ($imageInfo === false) {
            return ['success' => false, 'message' => 'Arquivo não é uma imagem válida'];
        }
        
        list($width, $height, $type) = $imageInfo;
        
        // Criar imagem a partir do arquivo
        switch ($type) {
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $sourceImage = imagecreatefromgif($sourcePath);
                break;
            case IMAGETYPE_WEBP:
                $sourceImage = imagecreatefromwebp($sourcePath);
                break;
            default:
                return ['success' => false, 'message' => 'Formato de imagem não suportado'];
        }
        
        if ($sourceImage === false) {
            return ['success' => false, 'message' => 'Erro ao carregar imagem'];
        }
        
        // Calcular novas dimensões mantendo aspect ratio
        $ratio = $width / $height;
        $targetRatio = $maxWidth / $maxHeight;
        
        if ($ratio > $targetRatio) {
            // Imagem mais larga
            $newWidth = $maxWidth;
            $newHeight = (int)($maxWidth / $ratio);
        } else {
            // Imagem mais alta
            $newHeight = $maxHeight;
            $newWidth = (int)($maxHeight * $ratio);
        }
        
        // Não aumentar imagens pequenas, apenas reduzir
        if ($newWidth > $width && $newHeight > $height) {
            $newWidth = $width;
            $newHeight = $height;
        }
        
        // Criar nova imagem redimensionada
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preservar transparência para PNG
        if ($type == IMAGETYPE_PNG) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
            imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // Redimensionar
        imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        // Determinar caminho de destino
        if ($destPath === null) {
            $destPath = $sourcePath;
        }
        
        // Salvar com compressão progressiva
        $tempPath = $destPath . '.tmp';
        $currentQuality = $quality;
        $attempts = 0;
        $maxAttempts = 5;
        
        do {
            // Salvar como JPEG (melhor compressão)
            $saved = imagejpeg($newImage, $tempPath, $currentQuality);
            
            if (!$saved) {
                imagedestroy($sourceImage);
                imagedestroy($newImage);
                return ['success' => false, 'message' => 'Erro ao salvar imagem'];
            }
            
            $fileSize = filesize($tempPath);
            $fileSizeKB = $fileSize / 1024;
            
            // Se está dentro do limite, OK
            if ($fileSizeKB <= $maxSizeKB) {
                break;
            }
            
            // Reduzir qualidade para próxima tentativa
            $currentQuality -= 10;
            $attempts++;
            
        } while ($fileSizeKB > $maxSizeKB && $currentQuality >= 60 && $attempts < $maxAttempts);
        
        // Mover arquivo temporário para destino final
        rename($tempPath, $destPath);
        
        // Liberar memória
        imagedestroy($sourceImage);
        imagedestroy($newImage);
        
        $finalSize = filesize($destPath);
        $finalSizeKB = $finalSize / 1024;
        
        return [
            'success' => true,
            'message' => 'Imagem otimizada com sucesso',
            'path' => $destPath,
            'size' => $finalSize,
            'sizeKB' => round($finalSizeKB, 2),
            'dimensions' => ['width' => $newWidth, 'height' => $newHeight],
            'quality' => $currentQuality
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
    }
}

