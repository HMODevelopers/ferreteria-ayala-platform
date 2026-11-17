import { spawn } from 'node:child_process';
import { createInterface } from 'node:readline';
import { runM09Browser } from './m09-admin-browser.mjs';
const name = 'ayala-m09-http-' + process.pid;
const useDocker = process.env.M09_HTTP_USE_DOCKER === '1';
const command = useDocker ? 'docker' : process.execPath.replace(/node(?:\.exe)?$/i, 'php');
const args = useDocker
  ? ['run','--rm','-i','--name',name,'--network','mysql_default','-p','127.0.0.1:8088:8088','-v',process.cwd()+':/var/www/html','-w','/var/www/html','ferreteria-ayala-php','php','tests/mysql-m09-http-integration.php']
  : ['tests/mysql-m09-http-integration.php'];
const proc = spawn(command, args, {windowsHide:true,stdio:['pipe','pipe','pipe']});
let browserTask; let failure; let errors='';
proc.stderr.on('data', chunk => { errors += chunk; });
const lines=createInterface({input:proc.stdout});
lines.on('line',line=>{
  if(line.startsWith('M09_READY ')){
    browserTask=runM09Browser(JSON.parse(line.slice(10))).then(()=>{proc.stdin.write('PASS\n');proc.stdin.end();}).catch(error=>{failure=error;proc.stdin.write('FAIL\n');proc.stdin.end();});
  }else console.log(line);
});
const code=await new Promise(resolve=>proc.on('close',resolve));
await browserTask;
if(errors)process.stderr.write(errors);
if(failure)console.error(failure);
process.exitCode=code||failure?1:0;
