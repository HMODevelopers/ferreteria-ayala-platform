<?php
declare(strict_types=1);
namespace App\Data;
final class DemoHomeData {
    public static function all(): array {
        $categories = [
            ['id' => 'construccion', 'name' => 'Construcción', 'description' => 'La base de un gran proyecto.', 'image' => 'cement.svg'],
            ['id' => 'herramientas', 'name' => 'Herramientas', 'description' => 'Precisión en cada trabajo.', 'image' => 'drill.svg'],
            ['id' => 'plomeria', 'name' => 'Plomería', 'description' => 'Soluciones que conectan.', 'image' => 'pipe.svg'],
            ['id' => 'electrico', 'name' => 'Eléctrico', 'description' => 'Energía para tus ideas.', 'image' => 'cable.svg'],
            ['id' => 'herreria', 'name' => 'Herrería', 'description' => 'Resistencia en cada detalle.', 'image' => 'steel.svg'],
            ['id' => 'pinturas', 'name' => 'Pinturas', 'description' => 'Dale vida a tus espacios.', 'image' => 'paint.svg'],
        ];
        $products = [
            ['id' => 1, 'sku' => 'DEMO-HER-001', 'name' => 'Taladro inalámbrico 20 V', 'category' => 'Herramientas', 'category_id' => 'herramientas', 'brand' => null, 'unit' => 'Pieza', 'image' => 'drill.svg', 'price' => null, 'badge' => 'Selección demo', 'published' => true],
            ['id' => 2, 'sku' => 'DEMO-CON-002', 'name' => 'Cemento para uso general', 'category' => 'Construcción', 'category_id' => 'construccion', 'brand' => null, 'unit' => 'Saco de referencia', 'image' => 'cement.svg', 'price' => null, 'badge' => null, 'published' => true],
            ['id' => 3, 'sku' => 'DEMO-PIN-003', 'name' => 'Pintura vinílica blanca', 'category' => 'Pinturas', 'category_id' => 'pinturas', 'brand' => null, 'unit' => 'Cubeta de referencia', 'image' => 'paint.svg', 'price' => null, 'badge' => null, 'published' => true],
            ['id' => 4, 'sku' => 'DEMO-PLO-004', 'name' => 'Conexión de PVC hidráulico', 'category' => 'Plomería', 'category_id' => 'plomeria', 'brand' => null, 'unit' => 'Pieza', 'image' => 'pipe.svg', 'price' => null, 'badge' => null, 'published' => true],
            ['id' => 5, 'sku' => 'DEMO-ELE-005', 'name' => 'Cable para instalación eléctrica', 'category' => 'Eléctrico', 'category_id' => 'electrico', 'brand' => null, 'unit' => 'Metro de referencia', 'image' => 'cable.svg', 'price' => null, 'badge' => null, 'published' => true],
            ['id' => 6, 'sku' => 'DEMO-HIE-006', 'name' => 'Perfil de acero estructural', 'category' => 'Herrería', 'category_id' => 'herreria', 'brand' => null, 'unit' => 'Tramo de referencia', 'image' => 'steel.svg', 'price' => null, 'badge' => null, 'published' => true],
        ];
        $slides = [
            ['id' => 'build', 'title' => 'GRANDES PROYECTOS.\nMEJORES CIMIENTOS.', 'subtitle' => 'De la primera idea al último detalle. Encuentra los materiales y herramientas para construir lo mejor.', 'image_desktop' => 'construction.jpg', 'image_mobile' => 'construction-mobile.jpg', 'button' => 'Explorar materiales', 'href' => '#categorias', 'type' => 'brand', 'status' => 'demo', 'priority' => 1, 'starts_at' => null, 'ends_at' => null, 'alignment' => 'left', 'contrast' => 'dark', 'eyebrow' => 'TODO EMPIEZA CON UNA BUENA BASE'],
            ['id' => 'tools', 'title' => 'EL EQUIPO CORRECTO.\nTU SIGUIENTE LOGRO.', 'subtitle' => 'Herramientas que acompañan tus ideas. Explora una selección de referencia para tu próximo trabajo.', 'image_desktop' => 'tools.jpg', 'image_mobile' => 'tools-mobile.jpg', 'button' => 'Explorar herramientas', 'href' => '#productos', 'type' => 'tools', 'status' => 'demo', 'priority' => 2, 'starts_at' => null, 'ends_at' => null, 'alignment' => 'left', 'contrast' => 'dark', 'eyebrow' => 'IDEAS QUE SE CONVIERTEN EN PROYECTOS'],
            ['id' => 'work', 'title' => 'DALE FORMA\nA LO QUE IMAGINAS.', 'subtitle' => 'Construcción, herrería y soluciones para cada etapa. Prepara tu lista de materiales en un solo lugar.', 'image_desktop' => 'workshop.jpg', 'image_mobile' => 'workshop-mobile.jpg', 'button' => 'Preparar mi lista', 'href' => '#cotiza', 'type' => 'quote', 'status' => 'demo', 'priority' => 3, 'starts_at' => null, 'ends_at' => null, 'alignment' => 'left', 'contrast' => 'dark', 'eyebrow' => 'DE TU TALLER A TU PRÓXIMO PROYECTO'],
        ];
        return [
            'title' => 'Ferretería Ayala | Construye lo mejor',
            'site' => ['name' => 'Ferretería Ayala', 'tagline' => 'CONSTRUYE LO MEJOR', 'phone' => null, 'email' => null, 'socials' => [], 'demo_notice' => 'Vista de demostración · Catálogo y campañas de referencia.'],
            'navigation' => [['label' => 'Inicio', 'href' => '/'], ['label' => 'Nosotros', 'href' => '/#nosotros'], ['label' => 'Ferretería', 'href' => '/#productos'], ['label' => 'Plomería', 'href' => '/#productos', 'filter' => 'plomeria'], ['label' => 'Herrería', 'href' => '/#productos', 'filter' => 'herreria'], ['label' => 'Construcción', 'href' => '/#productos', 'filter' => 'construccion'], ['label' => 'Eléctrico', 'href' => '/#productos', 'filter' => 'electrico']],
            'slides' => $slides, 'categories' => $categories, 'products' => $products,
            'brands' => ['TRUPER', 'CEMEX', 'SAYER', 'coflex'],
            'promotions' => [
                ['id' => 1, 'title' => 'Equipa tu próximo proyecto.', 'description' => 'Una selección pensada para quienes hacen que las cosas sucedan.', 'image' => 'tools.jpg', 'label' => 'HERRAMIENTAS', 'href' => '#productos', 'starts_at' => null, 'ends_at' => null, 'branch_id' => null, 'category_id' => 'herramientas', 'status' => 'demo'],
                ['id' => 2, 'title' => 'De los cimientos al acabado.', 'description' => 'Explora materiales para cada etapa de tu obra.', 'image' => 'construction.jpg', 'label' => 'CONSTRUCCIÓN', 'href' => '#categorias', 'starts_at' => null, 'ends_at' => null, 'branch_id' => null, 'category_id' => 'construccion', 'status' => 'demo'],
            ],
            'events' => [
                ['id' => 1, 'title' => 'Herramientas en acción', 'description' => 'Ejemplo de una demostración práctica para conocer herramientas y sus aplicaciones.', 'image' => 'tools.jpg', 'date' => null, 'location' => 'Sucursal por definir', 'type' => 'DEMOSTRACIÓN', 'status' => 'demo'],
                ['id' => 2, 'title' => 'Construir con conocimiento', 'description' => 'Ejemplo de una capacitación sobre materiales y buenas prácticas de construcción.', 'image' => 'construction.jpg', 'date' => null, 'location' => 'Sucursal por definir', 'type' => 'CAPACITACIÓN', 'status' => 'demo'],
                ['id' => 3, 'title' => 'Nuevas ideas para tu taller', 'description' => 'Ejemplo de una novedad editorial dedicada a proyectos de herrería.', 'image' => 'workshop.jpg', 'date' => null, 'location' => 'Contenido de referencia', 'type' => 'NOVEDADES', 'status' => 'demo'],
            ],
            'reviews' => [
                ['name' => 'Cliente de ejemplo 01', 'rating' => 5, 'comment' => 'Un lugar donde encontrar todo lo que necesitas para darle forma a tu proyecto.', 'date' => null, 'verified' => false, 'status' => 'demo'],
                ['name' => 'Cliente de ejemplo 02', 'rating' => 5, 'comment' => 'La atención y la orientación hacen la diferencia al elegir tus materiales.', 'date' => null, 'verified' => false, 'status' => 'demo'],
                ['name' => 'Cliente de ejemplo 03', 'rating' => 5, 'comment' => 'Desde una reparación hasta una obra, es bueno contar con el equipo adecuado.', 'date' => null, 'verified' => false, 'status' => 'demo'],
            ],
            'branches' => array_map(static fn(string $name): array => ['id' => strtolower($name), 'name' => $name, 'address' => null, 'phone' => null, 'hours' => null, 'map_url' => null, 'image' => null], ['Matriz', 'Boulevard', 'Centro', 'Magdalena']),
        ];
    }
}
