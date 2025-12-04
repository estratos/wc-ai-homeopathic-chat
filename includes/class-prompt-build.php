<?php
/**
 * Class WC_AI_Chat_Prompt_Build
 * Contiene métodos para construcción de prompts
 * Version: 2.5.3
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_AI_Chat_Prompt_Build {
    
    /**
     * Construye prompt mejorado - VERSIÓN 2.5.3
     * CON INSTRUCCIONES MÁS CLARAS SOBRE CUÁNDO MOSTRAR SOLO PRODUCTOS ESPECÍFICOS
     */
    public function build_prompt_mejorado($message, $analysis, $relevant_products, $info_productos_mencionados = "", $productos_mencionados = array(), $mostrar_solo_productos_mencionados = false) {
        $categorias_text = !empty($analysis['categorias_detectadas']) ? 
            "CATEGORÍAS DETECTADAS: " . implode(', ', $analysis['categorias_detectadas']) : 
            "No se detectaron categorías específicas.";
        
        $padecimientos_text = !empty($analysis['padecimientos_encontrados']) ? 
            "PADECIMIENTOS IDENTIFICADOS: " . implode(', ', array_column(array_slice($analysis['padecimientos_encontrados'], 0, 8), 'padecimiento')) : 
            "No se identificaron padecimientos específicos.";
        
        $hay_productos_mencionados = !empty($productos_mencionados);
        $instrucciones_especiales = "";
        
        if ($hay_productos_mencionados) {
            $nombres_productos = array();
            foreach ($productos_mencionados as $item) {
                $nombres_productos[] = $item['product']->get_name();
            }
            
            $instrucciones_especiales = "\n\n🚨 INFORMACIÓN ESPECIAL - PRODUCTOS MENCIONADOS:\nEl usuario ha mencionado o mostrado interés en estos productos específicos: " . implode(', ', $nombres_productos);
            
            if ($mostrar_solo_productos_mencionados) {
                $instrucciones_especiales .= "\n\n🎯 INSTRUCCIÓN CRÍTICA: El usuario pregunta específicamente por estos productos. DEBES:\n" .
                    "1. PROPORCIONAR INFORMACIÓN DETALLADA SOLO de los productos mencionados\n" .
                    "2. INCLUIR OBLIGATORIAMENTE: precio, SKU, disponibilidad, descripción breve\n" .
                    "3. NO RECOMENDAR otros productos adicionales\n" .
                    "4. Si el producto no está disponible, ser honesto y sugerir consultar alternativas con un profesional\n" .
                    "5. SIEMPRE incluir el precio exacto y el código SKU en la respuesta\n" .
                    "6. LIMITAR la respuesta a máximo 3-4 productos principales";
            } else {
                $instrucciones_especiales .= "\n\n💡 INSTRUCCIONES ADICIONALES:\n" .
                    "1. Proporciona información sobre los productos mencionados INCLUYENDO PRECIO Y SKU\n" .
                    "2. También puedes sugerir productos complementarios si son relevantes para los síntomas\n" .
                    "3. Relaciona los productos mencionados con los síntomas descritos\n" .
                    "4. NO OLVIDES incluir precio y SKU de todos los productos mencionados\n" .
                    "5. Prioriza los productos más relevantes para los síntomas del usuario\n" .
                    "6. LIMITA la respuesta a 3-4 productos principales para no abrumar al usuario";
            }
        }
        
        $prompt = "Eres un homeópata experto. Analiza la consulta y proporciona información útil sobre productos homeopáticos.";

        if ($hay_productos_mencionados) {
            $prompt .= "\n\n{$info_productos_mencionados}";
        }

        $prompt .= "\n\nCONSULTA DEL PACIENTE:\n\"{$message}\"\n\nANÁLISIS DE SÍNTOMAS:\n{$analysis['resumen_analisis']}\n{$categorias_text}\n{$padecimientos_text}";
        
        // Solo incluir productos recomendados si no estamos mostrando solo productos mencionados
        if (!$mostrar_solo_productos_mencionados && !empty($relevant_products)) {
            $prompt .= "\n\nINVENTARIO DE PRODUCTOS RECOMENDADOS:\n{$relevant_products}";
        }
        
        $prompt .= "{$instrucciones_especiales}\n\nINSTRUCCIONES GENERALES CRÍTICAS:\n" .
            "1. Proporciona información CLARA y DIRECTA\n" .
            "2. Usa formato legible con saltos de línea\n" .
            "3. INCLUYE OBLIGATORIAMENTE información específica de productos: PRECIO, SKU, disponibilidad\n" .
            "4. SIEMPRE menciona el precio y SKU cuando hables de un producto específico\n" .
            "5. Sé empático pero profesional\n" .
            "6. Siempre aclara: \"Consulta con un profesional de la salud para diagnóstico preciso\"\n" .
            "7. " . ($mostrar_solo_productos_mencionados ? 
                "RESPONDE EXCLUSIVAMENTE sobre los productos que el usuario mencionó INCLUYENDO PRECIO Y SKU - NO RECOMIENDES OTROS PRODUCTOS" : 
                "Si el usuario solo describe síntomas, recomienda productos relevantes basados en el análisis (máximo 3-4 productos)") . 
            "\n\nResponde en español de manera natural y práctica. Usa formato claro y fácil de leer.";

        return $prompt;
    }
    
    /**
     * Obtiene información detallada de productos mencionados - MEJORADA
     * INCLUYE PRECIO Y SKU OBLIGATORIAMENTE
     */
    public function get_info_productos_mencionados($productos_mencionados) {
        if (empty($productos_mencionados)) {
            return "";
        }
        
        $info = "🎯 PRODUCTOS ESPECÍFICOS MENCIONADOS EN LA CONSULTA:\n\n";
        
        foreach ($productos_mencionados as $item) {
            $product = $item['product'];
            $info .= $this->format_detailed_product_info($product, $item) . "\n---\n";
        }
        
        $info .= "\n💡 INFORMACIÓN IMPORTANTE:\n- Precios en " . get_woocommerce_currency_symbol() . "\n- Disponibilidad sujeta a stock\n- SKU único para cada producto\n- INCLUYE PRECIO Y SKU EN TODAS LAS RESPUESTAS";
        return $info;
    }
    
    /**
     * Formatea información detallada del producto - MEJORADA
     * INCLUYE PRECIO Y SKU DE FORMA DESTACADA
     */
    private function format_detailed_product_info($product, $detection_info = null) {
        $title = $product->get_name();
        $sku = $product->get_sku() ?: 'No disponible';
        $price = $product->get_price_html();
        $regular_price = $product->get_regular_price();
        $sale_price = $product->get_sale_price();
        $short_description = wp_strip_all_tags($product->get_short_description() ?: '');
        $description = wp_strip_all_tags($product->get_description() ?: '');
        $stock_status = $product->get_stock_status();
        $stock_quantity = $product->get_stock_quantity();
        $product_url = get_permalink($product->get_id());
        
        // Información de stock mejorada
        if ($stock_status === 'instock') {
            $stock_text = $stock_quantity ? "✅ En stock ({$stock_quantity} unidades)" : "✅ Disponible";
        } else {
            $stock_text = "⏳ Consultar disponibilidad";
        }
        
        // Información de precio detallada - DESTACADA
        $price_info = "💰 PRECIO: {$price}";
        if ($sale_price && $regular_price != $sale_price) {
            $descuento = round((($regular_price - $sale_price) / $regular_price) * 100);
            $price_info .= " 🎁 {$descuento}% OFF";
        }
        
        // Información de detección
        $detection_text = "";
        if ($detection_info) {
            $confianza_porcentaje = round($detection_info['confianza'] * 100);
            $detection_text = "🔍 Detectado por: {$detection_info['tipo_coincidencia']} ({$confianza_porcentaje}% confianza)\n";
        }
        
        // Descripción breve (limitada)
        $desc_text = "";
        if ($short_description) {
            $desc_clean = preg_replace('/\s+/', ' ', $short_description);
            if (strlen($desc_clean) > 120) {
                $desc_clean = substr($desc_clean, 0, 117) . '...';
            }
            $desc_text = "📝 {$desc_clean}\n";
        }
        
        // Construir información detallada con PRECIO Y SKU DESTACADOS
        $info = "🟢 PRODUCTO: {$title}\n";
        $info .= $detection_text;
        $info .= "🆔 SKU: {$sku}\n"; // SKU DESTACADO
        $info .= "{$price_info}\n"; // PRECIO DESTACADO
        $info .= "📊 Stock: {$stock_text}\n";
        $info .= $desc_text;
        $info .= "🔗 Enlace: {$product_url}";
        
        return $info;
    }
    
    /**
     * Determina si debe mostrar solo los productos mencionados - VERSIÓN MEJORADA 2.5.3
     */
    public function debe_mostrar_solo_productos_mencionados($productos_mencionados, $message) {
        if (empty($productos_mencionados)) {
            return false;
        }
        
        // Si hay al menos un producto con alta confianza (>0.9), mostrar solo esos
        foreach ($productos_mencionados as $producto) {
            if ($producto['confianza'] >= 0.9) {
                error_log("WC AI Chat Debug - Producto con alta confianza detectado: " . $producto['product']->get_name() . " - Confianza: " . $producto['confianza']);
                return true;
            }
        }
        
        // ANÁLISIS MEJORADO: Verificar si el usuario realmente está preguntando por productos específicos
        $message_lower = strtolower($message);
        $palabras_especificas = array(
            'comprar', 'precio de', 'cuesta', 'vale', 'cotizar', 'cotización',
            'quiero', 'deseo', 'necesito', 'busco', 'estoy interesado en',
            'tienen', 'venden', 'disponible', 'disponen', 'cuánto', 'qué precio'
        );
        
        $es_consulta_especifica = false;
        foreach ($palabras_especificas as $palabra) {
            if (strpos($message_lower, $palabra) !== false) {
                $es_consulta_especifica = true;
                error_log("WC AI Chat Debug - Palabra específica detectada: " . $palabra);
                break;
            }
        }
        
        // Si el usuario está preguntando específicamente por productos y tenemos detecciones
        if ($es_consulta_especifica && !empty($productos_mencionados)) {
            error_log("WC AI Chat Debug - Consulta específica detectada, mostrando solo productos mencionados");
            return true;
        }
        
        return false;
    }
}