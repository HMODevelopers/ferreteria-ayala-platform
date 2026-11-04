import { spawn } from 'node:child_process';
import { createInterface } from 'node:readline';
import { runM10Browser } from './m10-admin-browser.mjs';
const name = 'ayala-m10-http-' + process.pid;
const proc = spawn('docker', ['run','--rm','-i','--name',name,'--network','mysql_default','-p','127.0.0.1:8088:8088','-v',process.cwd()+':/var/www/html','-w','/var/www/html','ferreteria-ayala-php','php','tests/mysql-m10-http-integration.php'], {windowsHide:true,stdio:['pipe','pipe','pipe']});
let browserTask; let failure; let errors='';
proc.stderr.on('data', chunk => { errors += chunk; });
const lines=createInterface({input:proc.stdout});
lines.on('line',line=>{
  if(line.startsWith('M10_READY ')){
    browserTask=runM10Browser(JSON.parse(line.slice(10))).then(()=>{proc.stdin.write('PASS\n');proc.stdin.end();}).catch(error=>{failure=error;proc.stdin.write('FAIL\n');proc.stdin.end();});
  }else console.log(line);
});
const code=await new Promise(resolve=>proc.on('close',resolve));
await browserTask;
if(errors)process.stderr.write(errors);
if(failure)console.error(failure);
process.exitCode=code||failure?1:0;
