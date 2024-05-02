<?php

class Telegram
{

    public function send(string $msg)
    {

        file_get_contents(
            'https://api.telegram.org/<token>/sendMessage?chat_id=<chat_id>&text=' . $msg
        );
    }
}
