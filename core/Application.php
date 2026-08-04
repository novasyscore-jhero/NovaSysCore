<?php

namespace NovaSysCore;
use Exception;
use App\Models\User;

class Application
{
    protected Container $container;


    public function __construct()
    {
        $this->container = new Container();

        $this->container->bind(
            "router",
            function () {
                return new Router();
            }
        );

        $this->container->bind(
            "audit",
            function () {
                return new AuditLogger();
            }
        );
    }


    public function container(): Container
    {
        return $this->container;
    }


    public function start(): string
    {
        $router = $this->container->make("router");


        /* $router->get("/", function(){

            echo "Sistema: " . Config::get('app.name') . " 🚀";

        }); */

        /* $router->get('/', function () {

            $user = new User();
        
            $id = $user->create([
                'company_id' => 1,
                'name' => 'Administrador',
                'email' => 'admin@novasyscore.com',
                'password' => password_hash('123456', PASSWORD_DEFAULT)
            ]);
        
            echo "Usuario creado con ID: {$id} 🚀";
        
        }); */

        /* $router->get("/", function(){

            $migrator = new \NovaSysCore\Database\Migrator();
        
            $migrator->ensureMigrationsTable();
        
            echo "Sistema de migraciones funcionando 🚀";
        
        }); */

        /* $router->get("/", function(){

            $migrator = new \NovaSysCore\Database\Migrator();
        
            $migrator->run();
        
            echo "Migraciones ejecutadas 🚀";
        
        }); */

        $router->get("/", function(){

            $migrator = new \NovaSysCore\Database\Migrator();
        
            $migrator->rollback();
        
            echo "Rollback ejecutado 🚀";
        
        });


        ob_start();

        $router->dispatch(
            "/",
            "GET"
        );


        return ob_get_clean();
    }
}