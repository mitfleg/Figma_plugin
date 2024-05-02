import aiomysql
from bot import DATABASE


class DataBase:
    async def connect(self):
        self.conn = await aiomysql.connect(**DATABASE, cursorclass=aiomysql.cursors.DictCursor)
        self.cursor = await self.conn.cursor()

    async def execute_query(self, query, args=None):
        await self.cursor.execute(query, args)
        await self.conn.commit()
        return await self.cursor.fetchall()

    async def create(self, table, columns, values):
        column_names = ", ".join(columns)
        placeholders = ", ".join(["%s"] * len(columns))
        query = f"INSERT INTO {table} ({column_names}) VALUES ({placeholders})"
        await self.execute_query(query, values)
        return True

    async def read(self, table, columns=None, where=None, join=None):
        column_names = ", ".join(columns) if columns else "*"
        query = f"SELECT {column_names} FROM {table}"
        if where:
            query += f" WHERE {where}"
        return await self.execute_query(query)

    async def update(self, table, set_columns, set_values, where=None):
        set_statements = ", ".join([f"{col}=%s" for col in set_columns])
        query = f"UPDATE {table} SET {set_statements}"
        if where:
            query += f" WHERE {where}"
        await self.execute_query(query, set_values)

    async def delete(self, table, where=None):
        query = f"DELETE FROM {table}"
        if where:
            query += f" WHERE {where}"
        await self.execute_query(query)

    async def getUsers(self):
        query = """
            SELECT 
                users.*, 
                ip_addresses.ip_address AS ip, 
                ip_addresses.country, 
                subscriptions.end_date AS subscribe_end_date
            FROM users 
            LEFT JOIN ip_addresses 
                ON users.ip_id = ip_addresses.id
            LEFT JOIN subscriptions 
                ON users.figma_id = subscriptions.figma_id 
                AND subscriptions.start_date != '0000-00-00 00:00:00'
        """
        return await self.execute_query(query)

    async def getPromocode(self):
        intervals = ['1 day', '1 week', '1 month', '1 year']
        promocodes = []

        for interval in intervals:
            query = """
                SELECT 
                    code, expiry_interval
                FROM promocodes 
                WHERE status = 'UNUSED' AND expiry_interval = %s
                LIMIT 1
            """
            promocode = await self.execute_query(query, interval)
            promocodes.append(promocode[0])
        
        return promocodes

    async def __aenter__(self):
        await self.connect()
        return self

    async def __aexit__(self, *args):
        self.conn.close()