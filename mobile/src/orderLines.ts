import type { AddonCounts, Order } from './api';

/**
 * Кошик для «Повторити замовлення» з відповіді сервера: працює й для замовлень з іншого пристрою.
 * Видалені товари (product_id 0) і відповіді старого сервера без product_id пропускаємо.
 * Спільне для застосунку й сайту (тема raky-site імпортує цей файл).
 */
export function linesFromOrder(order: Order): { productId: number; flavor: string; quantity: number; addons?: AddonCounts }[] {
  return order.items
    .filter((item) => (item.product_id ?? 0) > 0 && item.quantity > 0)
    .map((item) => ({
      productId: item.product_id!,
      flavor: item.flavor,
      quantity: item.quantity,
      addons: Object.fromEntries((item.addons ?? []).map((addon) => [addon.name, addon.quantity])),
    }));
}
