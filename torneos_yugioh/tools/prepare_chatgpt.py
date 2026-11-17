"""Generate private OAuth setup outside source control; never print secrets."""
from pathlib import Path
import argparse, json, secrets, subprocess

def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--php',required=True)
    args=parser.parse_args()
    repo=Path(__file__).resolve().parents[2]
    out=repo/'outputs/chatgpt-connection-20261103'
    out.mkdir(parents=True,exist_ok=True)
    private=out/'yugioh-chatgpt-config.php'
    if private.exists():
        print('Private setup already exists; preserved.');return
    code=secrets.token_urlsafe(36)
    result=subprocess.run([args.php,'-r',"echo password_hash(trim(stream_get_contents(STDIN)), PASSWORD_DEFAULT);"],input=code,text=True,capture_output=True,check=True)
    password_hash=result.stdout.strip()
    if not password_hash.startswith('$2y$'):raise RuntimeError('Unexpected password hash.')
    config="<?php\ndeclare(strict_types=1);\nreturn [\n    'resource'=>'https://stationxtulua.com/yugioh/mcp/',\n    'owner_password_hash'=>"+repr(password_hash)+",\n    'state_dir'=>__DIR__.'/yugioh-chatgpt-state',\n];\n"
    private.write_bytes(config.encode())
    instructions=('STATION X - CONEXION PRIVADA CON CHATGPT\n\n'
        'URL MCP: https://stationxtulua.com/yugioh/mcp/\n'
        'Nombre: Station X Yu-Gi-Oh\nAutenticacion: OAuth (CIMD o DCR). No requiere API key.\n\n'
        'CODIGO PRIVADO (pegar solo en la pantalla de autorizacion de stationxtulua.com):\n'+code+'\n\n'
        'ChatGPT web > Plugins > + > Agregar servidor MCP personalizado.\n'
        'Crear, autorizar lectura con el codigo anterior, instalar y seleccionar con @ en un chat.\n'
        'Si el menu no esta disponible, revisar permisos y disponibilidad de la cuenta/espacio.\n'
        'No pegar este codigo en chats, repositorios ni capturas.\n'
        'Alcance: lectura de decks, cartas, guias publicadas y combos propios.\n'
        'El servidor no llama modelos de OpenAI. Se aplican los limites de ChatGPT.\n')
    (out/'CONEXION_CHATGPT_PRIVADA.txt').write_bytes(instructions.encode('utf-8'))
    print(json.dumps({'generated':True,'directory':str(out),'secrets_printed':False}))

if __name__=='__main__':main()
