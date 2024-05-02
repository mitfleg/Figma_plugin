from aiogram.types import InlineKeyboardButton, InlineKeyboardMarkup


async def main_keyboard():
    markup = InlineKeyboardMarkup(row_width=2)
    registr = InlineKeyboardButton('🏷 Получить промокод', callback_data='getPromocode')
    markup.add(registr)
    return markup


def back(page):
    return InlineKeyboardButton('🔙 Назад', callback_data=f'back_{page}')