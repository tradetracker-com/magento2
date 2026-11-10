<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\ProductData;

/**
 * Handles image selection and additional image processing for product feed data.
 */
class ImageProcessor
{
    /**
     * Add image data to a product based on image source configuration.
     *
     * @param int $storeId
     * @param array &$productData
     * @param string $imageSource The image source setting (e.g. 'all', specific type)
     * @param string $imageMain The main image selection strategy ('first', 'last', or image type)
     * @param string $allImagesConstant The constant value representing "all images" mode
     * @return void
     */
    public function addImageData(
        int $storeId,
        array &$productData,
        string $imageSource,
        string $imageMain,
        string $allImagesConstant
    ): void {
        $imageData = $productData['image_data'] ?? null;
        if ($imageData === null) {
            return;
        }

        if ($imageSource !== $allImagesConstant) {
            $this->setMainImage($storeId, $imageData, $productData, $imageSource);
            return;
        }

        $this->setMainImage($storeId, $imageData, $productData, $imageMain);
        $this->processImages($storeId, $imageData, $productData);
    }

    /**
     * Set the main image for the product based on type ('first', 'last', or specific type).
     *
     * @param int $storeId
     * @param array $imageData
     * @param array &$productData
     * @param string $type
     * @return void
     */
    private function setMainImage(int $storeId, array $imageData, array &$productData, string $type): void
    {
        foreach ([$storeId, 0] as $currentStoreId) {
            if (empty($imageData[$currentStoreId])) {
                continue;
            }

            ksort($imageData[$currentStoreId]);

            if (in_array($type, ['first', 'last', ''], true)) {
                $selectedImage = ($type === 'first' || ($type === ''))
                    ? reset($imageData[$currentStoreId])
                    : end($imageData[$currentStoreId]);

                if ($selectedImage) {
                    $productData['image'] = $selectedImage['file'];
                }
                break;
            }

            foreach ($imageData[$currentStoreId] as $image) {
                if (in_array($type, $image['types'], true)) {
                    $productData['image'] = $image['file'];
                    return;
                }
            }
        }
    }

    /**
     * Process and assign additional images to a product, limiting to 10.
     *
     * @param int $storeId
     * @param array $imageData
     * @param array &$productData
     * @return void
     */
    private function processImages(int $storeId, array $imageData, array &$productData): void
    {
        $productData['additional_images'] = $productData['additional_images'] ?? [];
        $maxAdditionalImages = 10;

        foreach ([$storeId, 0] as $currentStoreId) {
            if (empty($imageData[$currentStoreId])) {
                continue;
            }

            foreach ($imageData[$currentStoreId] as $image) {
                if (isset($productData['image']) && $image['file'] === $productData['image']) {
                    continue;
                }

                if (!in_array($image['file'], $productData['additional_images'], true)) {
                    $productData['additional_images'][] = $image['file'];
                }

                if (count($productData['additional_images']) >= $maxAdditionalImages) {
                    return;
                }
            }
        }
    }
}
