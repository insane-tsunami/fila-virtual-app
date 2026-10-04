import React from 'react';
import { BrowserRouter as Router, Switch, Route } from 'react-router-dom';

import Dashboard from './Dashboard';
import {
  SessaoProvider,
  RotaProtegida,
  RotaAnonima,
} from './sessao/SessaoProvider';
import QrCode from './Dashboard/Qrcode';
import Perfil from './Dashboard/Perfil';
import Cliente from './Cliente';
import Home from './Home';
import Login from './Login';
import Register from './Register';

import GlobalStyles from './styles/global';

function App() {
  return (
    <>
      <GlobalStyles />
      <Router>
        <SessaoProvider>
          <Switch>
            <Route exact path="/">
              <Home />
            </Route>
            <Route path="/cadastro">
              <RotaAnonima>
                <Register />
              </RotaAnonima>
            </Route>

            <Route path="/login">
              <RotaAnonima>
                <Login />
              </RotaAnonima>
            </Route>
            <Route exact path="/dashboard">
              <RotaProtegida>
                <Dashboard />
              </RotaProtegida>
            </Route>

            <Route path="/dashboard/qrcode">
              <RotaProtegida>
                <QrCode />
              </RotaProtegida>
            </Route>

            <Route path="/fila/:slug">
              <Cliente />
            </Route>

            <Route path="/dashboard/perfil">
              <RotaProtegida>
                <Perfil />
              </RotaProtegida>
            </Route>
          </Switch>
        </SessaoProvider>
      </Router>
    </>
  );
}

export default App;
