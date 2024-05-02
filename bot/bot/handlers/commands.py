from aiogram import types
from bot import dp
from .keyboard import main_keyboard
from ..database import DataBase



@dp.message_handler(commands="menu", is_admin=True)
async def cmdmenu(message: types.Message):
    await message.answer("🙋 <b>Админ панель!</b>", reply_markup=await main_keyboard(), parse_mode=types.ParseMode.HTML)



@dp.callback_query_handler(text="getPromocode", is_admin=True)
async def getPromocode(call: types.CallbackQuery):
    async with DataBase() as db:
        promocodes = await db.getPromocode()
        msg = '🎁 Список кодов\n\n'
        for promocode in promocodes:
            code = promocode['code']
            expiry_interval = promocode['expiry_interval']
            msg += f'🔑 Код: <code>{code}</code>\n'
            msg += f'⏳ Интервал: <b>{expiry_interval}</b>\n\n'
        await call.message.answer(msg, parse_mode=types.ParseMode.HTML)



