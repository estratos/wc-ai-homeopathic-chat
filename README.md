# WC AI Homeopathic Chat

Plugin de WordPress para integrar un chat de inteligencia artificial especializado en recomendaciones homeopáticas con WooCommerce.

## Características

- 🤖 Integración con DeepSeek AI para recomendaciones inteligentes
- 🛍️ Contexto completo de todos los productos de la tienda
- 📊 Muestreo inteligente de inventario (funciona con 50 o 5000 productos)
- 💾 Sistema de caché para mejor rendimiento
- 📱 Interfaz responsive y moderna
- ⚡ Optimizado para velocidad y eficiencia

## Instalación

1. Subir el plugin a `/wp-content/plugins/`
2. Activar el plugin en el panel de WordPress
3. Configurar la API Key de DeepSeek en Ajustes → Homeopathic Chat
4. El plugin analizará automáticamente todos tus productos

## Configuración

### API DeepSeek
1. Obtén una API Key en [DeepSeek](https://platform.deepseek.com/)
2. Ve a Ajustes → Homeopathic Chat en WordPress
3. Ingresa tu API Key y guarda los cambios

### Categorías Prioritarias
El plugin prioriza automáticamente productos en estas categorías:
- `homeopathic`
- `wellness` 
- `natural`
- `supplements`
- `health`

## Uso

### Shortcode
```php
[wc_ai_homeopathic_chat]