import {runCustomersBrowser} from './customers-auth-browser.mjs';
import {spawn} from 'node:child_process';
import {createInterface} from 'node:readline';
import {runM11Browser} from './m11-admin-browser.mjs';
const child=spawn('docker',['run','--rm','-i','--name','ayala-m11-http-'+process.pid,'--network','mysql_default','-p','127.0.0.1:8089:8089','-v',process.cwd()+':/var/www/html','-w','/var/www/html','ferreteria-ayala-php','php','tests/mysql-m11-http-integration.php'],{windowsHide:true,stdio:['pipe','pipe','pipe']});
let task,failure;let errors='';child.stderr.on('data',chunk=>{errors+=chunk});
createInterface({input:child.stdout}).on('line',line=>{if(line.startsWith('M11_READY ')){const fixture=JSON.parse(line.slice(10));task=runM11Browser(fixture).then(async checks=>{if(process.argv.includes('--customers'))checks.push(...(await runCustomersBrowser(fixture)).map(check=>'Customers: '+check));child.stdin.end(JSON.stringify({status:'PASS',checks})+'\n')}).catch(error=>{failure=error;child.stdin.end(JSON.stringify({status:'FAIL'})+'\n')});}else console.log(line)});
const code=await new Promise(resolve=>child.on('close',resolve));await task;if(errors)process.stderr.write(errors);if(failure)console.error(failure);process.exitCode=code||failure?1:0;
