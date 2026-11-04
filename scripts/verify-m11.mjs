import {spawn} from 'node:child_process';
import {readdir,readFile,writeFile,mkdir} from 'node:fs/promises';
import {createHash} from 'node:crypto';
const run=(command,args)=>new Promise((resolve,reject)=>{const p=spawn(command,args,{windowsHide:true,stdio:['ignore','pipe','pipe']});let output='';p.stdout.on('data',chunk=>{output+=chunk;process.stdout.write(chunk)});p.stderr.on('data',chunk=>process.stderr.write(chunk));p.on('error',reject);p.on('close',code=>code===0?resolve(output):reject(new Error(command+' failed with exit '+code)))});
async function walk(directory){let out=[];for(const e of await readdir(directory,{withFileTypes:true})){const file=directory+'/'+e.name;if(file==='tests/artifacts')continue;if(e.isDirectory())out.push(...await walk(file));else out.push(file)}return out}
try{
 const php=await run('docker',['exec','ferreteria-ayala-php-1','php','tests/m11-lint.php']);const domain=await run('docker',['exec','ferreteria-ayala-php-1','php','tests/run.php']);if(!php.includes('PHP syntax PASS:')||!/PASS: \d+ checks/.test(domain))throw new Error('Missing executed PHP result');
 const js=[...await walk('resources/js'),...await walk('scripts'),...await walk('tests')].filter(f=>/\.(?:js|mjs)$/.test(f));for(const f of js)await run(process.execPath,['--check',f]);console.log('JS syntax PASS: '+js.length+' files');
 await run('docker',['compose','--env-file','.env.example','run','--rm','assets','npm','run','build']);
 const names=[];for(const dir of ['app','config','database','resources','tests','scripts'])names.push(...(await walk(dir)).filter(f=>/\.(?:php|js|mjs|css)$/.test(f)));names.push('public/assets/css/app.css','public/assets/js/app.js','docker/Dockerfile','docker/uploads.ini','package.json');
 const files={};for(const f of names.sort())files[f]=createHash('sha256').update(await readFile(f)).digest('hex');await mkdir('tests/artifacts',{recursive:true});await writeFile('tests/artifacts/m11-local-pass.json',JSON.stringify({passed_at:Math.floor(Date.now()/1000),files,checks:['PHP syntax','JavaScript syntax','M00–M11 domain regression','Local assets build'],domain_checks:Number(domain.match(/PASS: (\d+) checks/)[1]),js_files:js.length},null,2));console.log('PASS: M11 local verification evidence recorded');
}catch(e){console.error(e.message);process.exitCode=1}
