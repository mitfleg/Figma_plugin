from aiogram import types
from aiogram.dispatcher.filters import BoundFilter
from bot import ADMINS


class IsAdminFilter(BoundFilter):
    key = 'is_admin'

    def __init__(self, is_admin):
        self.is_admin = is_admin

    async def check(self, message_or_call):
        if isinstance(message_or_call, types.Message):
            return message_or_call.chat.id in ADMINS
        elif isinstance(message_or_call, types.CallbackQuery):
            return message_or_call.from_user.id in ADMINS
        return False


def setup(dp):
    dp.filters_factory.bind(IsAdminFilter)
