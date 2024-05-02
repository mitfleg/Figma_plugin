import json
import logging
from aiogram import Bot, Dispatcher
from dotenv import load_dotenv
import os

logging.basicConfig(level=logging.INFO)
load_dotenv()

API_TOKEN = os.getenv("API_TOKEN")
DATABASE = json.loads(os.getenv("DATABASE"))
ADMINS = os.getenv("ADMIN").split(",")
ADMINS = [int(id) for id in ADMINS if id]

bot = Bot(token=API_TOKEN)
dp = Dispatcher(bot)

from bot.filters import setup as setup_filters
setup_filters(dp)
from bot import handlers
